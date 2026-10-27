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

class LeaveApplicationController extends Controller
{
    /**
     * Display the leave application form.
     */
    public function create(Request $request): View|RedirectResponse
    {
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
         * Show active leave types that have a positive balance.
         *
         * LWP is handled separately because it uses an annual
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
         * The approval service requires the assigned approver
         * to have the "approver" role.
         */
        $hasApprover = LeaveApproverMapping::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereHas('approver', function ($query) {
                $query->where('role', 'approver');
            })
            ->exists();

        return view('leave-applications.create', [
            'employee' => $employee,
            'leaveTypes' => $leaveTypes,
            'durations' => $durations,
            'hasApprover' => $hasApprover,
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
         * Do not accept employee_id from the form.
         * The employee is determined from the authenticated account.
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
             * Lock the employee row while checking and creating
             * the application. This helps prevent two simultaneous
             * submissions from both passing the pending check.
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
             * Only active leave types can be selected.
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
             * Enforce the rule that an employee cannot have
             * another pending leave application.
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
             * Validate the dates and duration using your existing
             * service. It also calculates the number of days to deduct.
             */
            $leaveDetails = $leaveRequestValidator->validate(
                $validated['leave_from_date'],
                $validated['leave_to_date'],
                (int) $validated['duration_id'],
                $validated['half_day_period'] ?? null
            );

            /*
             * Prevent the requested dates from overlapping
             * any existing approved leave.
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
             * Check eligibility without deducting any balance.
             *
             * LWP uses the service-year annual limit.
             * Other leave types use their balance allocations.
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
             * Find active approver mappings whose users have
             * the role required by LeaveApprovalService.
             */
            $approverMappings = LeaveApproverMapping::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('is_active', true)
                ->whereHas('approver', function ($query) {
                    $query->where('role', 'approver');
                })
                ->get();

            if ($approverMappings->isEmpty()) {
                throw ValidationException::withMessages([
                    'form' => [
                        'No active approver is assigned to your employee profile. Please contact the administrator.',
                    ],
                ]);
            }

            /*
             * Create the application as pending.
             * No leave balance is deducted at this stage.
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
             * Create one pending approval task for each active,
             * eligible approver mapping.
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
}
