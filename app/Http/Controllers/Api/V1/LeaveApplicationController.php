<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApproverMapping;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceEligibilityService;
use App\Services\Leave\LeaveRequestValidator;
use App\Services\Leave\LwpEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApplicationController extends Controller
{
    /**
     * List leave applications belonging to the authenticated employee.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile.',
            ], 403);
        }

        $applications = LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->with([
                'leaveType',
                'duration',
                'approvals.approverMapping.approver',
            ])
            ->latest()
            ->get();

        return response()->json([
            'data' => $applications,
        ]);
    }

    /**
     * Display one leave application belonging to the authenticated employee.
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile.',
            ], 403);
        }

        $application = LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->whereKey($id)
            ->with([
                'leaveType',
                'duration',
                'approvals.approverMapping.approver',
                'balanceTransactions',
            ])
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Leave application not found.',
            ], 404);
        }

        return response()->json([
            'data' => $application,
        ]);
    }

    /**
     * Cancel the authenticated employee's pending application.
     */
    public function cancel(
        Request $request,
        int $id,
        LeaveCancellationService $cancellationService
    ): JsonResponse {
        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $application = LeaveApplication::query()
            ->whereKey($id)
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Leave application not found.',
            ], 404);
        }

        $cancellationService->cancel(
            $application,
            $request->user(),
            $validated['cancellation_reason']
        );

        return response()->json([
            'message' => 'Leave application cancelled successfully.',
        ]);
    }

    public function store(
        Request $request,
        LeaveRequestValidator $leaveRequestValidator,
        LeaveBalanceEligibilityService $balanceEligibilityService,
        LwpEligibilityService $lwpEligibilityService
    ): JsonResponse {
        // Get the authenticated user.
        $user = $request->user();

        // Find the employee linked to this user.
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile.',
            ], 403);
        }

        // Validate the incoming API request.
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

        // Perform all checks and create the application in one transaction.
        $application = DB::transaction(function () use (
            $employee,
            $validated,
            $leaveRequestValidator,
            $balanceEligibilityService,
            $lwpEligibilityService
        ) {
            // Lock the employee row to prevent simultaneous submissions.
            $lockedEmployee = \App\Models\Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedEmployee->is_active) {
                throw ValidationException::withMessages([
                    'form' => ['Inactive employees cannot apply for leave.'],
                ]);
            }

            // Check whether the selected leave type is active.
            $leaveType = LeaveType::query()
                ->whereKey($validated['leave_type_id'])
                ->where('is_active', true)
                ->first();

            if (! $leaveType) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ['The selected leave type is inactive or unavailable.'],
                ]);
            }

            // Allow only one pending application per employee.
            $hasPendingApplication = \App\Models\LeaveApplication::query()
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

            // Validate dates, duration, half-day period and working days.
            $leaveDetails = $leaveRequestValidator->validate(
                $validated['leave_from_date'],
                $validated['leave_to_date'],
                (int) $validated['duration_id'],
                $validated['half_day_period'] ?? null
            );

            // Prevent overlapping with an already approved application.
            $hasApprovedOverlap = \App\Models\LeaveApplication::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('status', 'approved')
                ->whereDate('leave_from_date', '<=', $leaveDetails['to_date'])
                ->whereDate('leave_to_date', '>=', $leaveDetails['from_date'])
                ->exists();

            if ($hasApprovedOverlap) {
                throw ValidationException::withMessages([
                    'form' => [
                        'Your requested dates overlap with an already approved leave application.',
                    ],
                ]);
            }

            // Check the applicable leave eligibility service.
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
                    'leave_type_id' => [$eligibility['message']],
                ]);
            }

            // Find the employee's active approver mappings.
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

            // Create the leave application.
            $application = \App\Models\LeaveApplication::query()->create([
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

            // Create one pending approval task for each active approver mapping.
            foreach ($approverMappings as $mapping) {
                LeaveApplicationApproval::query()->create([
                    'leave_application_id' => $application->id,
                    'leave_approver_id' => $mapping->id,
                    'status' => 'pending',
                ]);
            }

            return $application;
        });

        // Load the related data for the API response.
        $application->load([
            'leaveType',
            'duration',
            'approvals.approverMapping.approver',
        ]);

        return response()->json([
            'message' => 'Leave application submitted successfully.',
            'data' => $application,
        ], 201);
    }
}
