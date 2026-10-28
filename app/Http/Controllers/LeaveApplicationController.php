<?php

namespace App\Http\Controllers;

use App\Models\Duration;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApproverMapping;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceEligibilityService;
use App\Services\Leave\LeaveRequestValidator;
use App\Services\Leave\LwpEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\LeaveBalance;
use App\Services\Leave\LeaveCancellationService;

class LeaveApplicationController extends Controller
{
    /**
     * Display the leave application form.
     */
    public function create(
        Request $request,
        LwpEligibilityService $lwpEligibilityService
    ): View|RedirectResponse {
        $employee = $request->user()->employee;

        if (! $employee) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Your account is not linked to an employee profile. Please contact the administrator.'
                );
        }

        if (! $employee->is_active) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Inactive employees cannot apply for leave.'
                );
        }

        /*
         * Display active leave types that have a positive balance.
         *
         * LWP is included separately because it uses an annual
         * service-year limit rather than a leave-balance allocation.
         */
        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->where('code', 'LWP')
                    ->orWhereHas('leaveBalances', function ($balanceQuery) use ($employee) {
                        $balanceQuery
                            ->where('employee_id', $employee->id)
                            ->where('remaining_days', '>', 0);
                    });
            })
            ->orderBy('name')
            ->get();

        $durations = Duration::query()
            ->whereIn('name', [
                'Full Day',
                'Half Day',
                'Multiple Days',
            ])
            ->orderBy('id')
            ->get();

        /*
         * An active mapping is sufficient.
         * The mapped user's role does not matter.
         */
        $hasApprover = LeaveApproverMapping::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        // Get today's date for checking which allocations are currently valid.
        $today = now()->toDateString();

        /*
 * LWP does not use leave_balances.
 * Calculate its allowance from approved LWP applications
 * for the employee's current service year.
 */
        $lwpBalance = $lwpEligibilityService->remainingForDate(
            $employee,
            $today
        );

        // Calculate the remaining balance for each leave type.
        $remainingLeaveDays = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('remaining_days', '>', 0)
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->selectRaw('leave_type_id, SUM(remaining_days) as remaining_days')
            ->groupBy('leave_type_id')
            ->pluck('remaining_days', 'leave_type_id');

        return view('leave-applications.create', [
            'employee' => $employee,
            'leaveTypes' => $leaveTypes,
            'durations' => $durations,
            'hasApprover' => $hasApprover,
            'remainingLeaveDays' => $remainingLeaveDays,
            'lwpBalance' => $lwpBalance,
        ]);
    }
    /**
     * Display the logged-in employee's leave application history.
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Find the employee profile linked to the logged-in user.
        $employee = $request->user()->employee;

        // Stop if this user does not have an employee profile.
        if (! $employee) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Your account is not linked to an employee profile.'
                );
        }

        // Fetch only this employee's leave applications.
        $applications = LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->with([
                'leaveType',
                'duration',
                'approvals.approverMapping.approver',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        // Display the application history page.
        return view('leave-applications.index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Validate and save a new leave application.
     */
    public function store(
        Request $request,
        LeaveRequestValidator $leaveRequestValidator,
        LeaveBalanceEligibilityService $balanceEligibilityService,
        LwpEligibilityService $lwpEligibilityService
    ): RedirectResponse {
        /*
         * Never accept employee_id from the form.
         * Determine the employee using the authenticated user.
         */
        $employee = $request->user()->employee;

        if (! $employee) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Your account is not linked to an employee profile. Please contact the administrator.'
                );
        }

        if (! $employee->is_active) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Inactive employees cannot apply for leave.'
                );
        }

        /*
         * Validate the incoming form fields.
         */
        $validated = $request->validate([
            'leave_type_id' => [
                'required',
                'integer',
                'exists:leavetypes,id',
            ],
            'duration_id' => [
                'required',
                'integer',
                'exists:durations,id',
            ],
            'leave_from_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'leave_to_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:leave_from_date',
            ],
            'half_day_period' => [
                'nullable',
                'in:first_half,second_half',
            ],
            'leavereason' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        DB::transaction(function () use (
            $employee,
            $validated,
            $leaveRequestValidator,
            $balanceEligibilityService,
            $lwpEligibilityService
        ) {
            /*
             * Lock the employee record while checking and creating
             * the application to help prevent concurrent duplicate
             * submissions.
             */
            $lockedEmployee = Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedEmployee->is_active) {
                throw ValidationException::withMessages([
                    'form' => [
                        'Inactive employees cannot apply for leave.',
                    ],
                ]);
            }

            /*
             * The selected leave type must exist and be active.
             */
            $leaveType = LeaveType::query()
                ->whereKey($validated['leave_type_id'])
                ->where('is_active', true)
                ->first();

            if (! $leaveType) {
                throw ValidationException::withMessages([
                    'leave_type_id' => [
                        'The selected leave type is inactive or unavailable.',
                    ],
                ]);
            }

            /*
             * An employee cannot submit another application while
             * an existing application is pending.
             *
             * Rejected and cancelled applications do not block
             * a new application.
             */
            $hasPendingApplication = LeaveApplication::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingApplication) {
                throw ValidationException::withMessages([
                    'form' => [
                        'You already have a pending leave application. Wait until it is decided before applying again.',
                    ],
                ]);
            }

            /*
             * Validate the dates and duration and calculate
             * the requested leave deduction.
             */
            $leaveDetails = $leaveRequestValidator->validate(
                $validated['leave_from_date'],
                $validated['leave_to_date'],
                (int) $validated['duration_id'],
                $validated['half_day_period'] ?? null
            );

            /*
             * Prevent overlap with previously approved leave.
             */
            $hasApprovedOverlap = LeaveApplication::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('status', 'approved')
                ->whereDate(
                    'leave_from_date',
                    '<=',
                    $leaveDetails['to_date']
                )
                ->whereDate(
                    'leave_to_date',
                    '>=',
                    $leaveDetails['from_date']
                )
                ->exists();

            if ($hasApprovedOverlap) {
                throw ValidationException::withMessages([
                    'form' => [
                        'Your requested dates overlap with an already approved leave application.',
                    ],
                ]);
            }

            /*
             * Check eligibility without deducting the balance.
             *
             * LWP uses the service-year limit.
             * Other leave types use their leave-balance allocations.
             */
            if ($leaveType->code === 'LWP') {
                $eligibility = $lwpEligibilityService->check(
                    $lockedEmployee,
                    $leaveDetails['from_date'],
                    $leaveDetails['to_date'],
                    (float) $leaveDetails['deduct_days']
                );
            } else {
                $eligibility = $balanceEligibilityService->check(
                    $lockedEmployee,
                    $leaveType->code,
                    $leaveDetails['from_date'],
                    $leaveDetails['to_date'],
                    (float) $leaveDetails['deduct_days']
                );
            }

            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'leave_type_id' => [
                        $eligibility['message'],
                    ],
                ]);
            }

            /*
             * Select every active mapping for this employee.
             *
             * No role check is performed. The active mapping
             * determines who can approve the application.
             */
            $approverMappings = LeaveApproverMapping::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('is_active', true)
                ->get();

            if ($approverMappings->isEmpty()) {
                throw ValidationException::withMessages([
                    'form' => [
                        'No active approver is assigned to your employee profile. Please contact the administrator.',
                    ],
                ]);
            }

            /*
             * Create the pending leave application.
             *
             * No balance is deducted during submission.
             */
            $application = LeaveApplication::query()->create([
                'employee_id' => $lockedEmployee->id,
                'leave_type_id' => $leaveType->id,
                'duration_id' => $leaveDetails['duration_id'],
                'half_day_period' => $leaveDetails['half_day_period'],
                'leave_from_date' => $leaveDetails['from_date'],
                'leave_to_date' => $leaveDetails['to_date'],
                'leavedeductcount' => $leaveDetails['deduct_days'],
                'leavereason' => trim($validated['leavereason']),
                'status' => 'pending',
            ]);

            /*
             * Create a pending approval task for each active mapping.
             */
            foreach ($approverMappings as $mapping) {
                LeaveApplicationApproval::query()->create([
                    'leave_application_id' => $application->id,
                    'leave_approver_id' => $mapping->id,
                    'status' => 'pending',
                ]);
            }
        });

        return redirect()
            ->route('leave-applications.create')
            ->with(
                'success',
                'Your leave application has been submitted successfully.'
            );
    }

    /**
     * Cancel the logged-in employee's pending leave application.
     */
    public function cancel(
        Request $request,
        LeaveApplication $leaveApplication,
        LeaveCancellationService $cancellationService
    ): RedirectResponse {
        // Validate the reason supplied by the employee.
        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        /*
     * The service checks ownership and verifies that the
     * application is still pending before cancelling it.
     */
        $cancellationService->cancel(
            $leaveApplication,
            $request->user(),
            $validated['cancellation_reason']
        );

        // Return to the employee's application history.
        return redirect()
            ->route('leave-applications.index')
            ->with(
                'success',
                'Your leave application has been cancelled successfully.'
            );
    }
}
