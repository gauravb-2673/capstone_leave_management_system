<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leave\SpecialLeaveAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use App\Models\LeaveAllocationDeletionAudit;
use Carbon\Carbon;

class LeaveAllocationController extends Controller
{
    /**
     * Show existing special MT/PT allocations.
     */
    /**
     * Show current special allocations and their deletion history.
     */
    public function index(): View
    {
        $allocations = LeaveBalance::query()
            ->with(['employee.user', 'leaveType'])
            ->where('allocation_type', 'special')
            ->whereHas('leaveType', function ($query) {
                $query->whereIn('code', ['MT', 'PT']);
            })
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'allocations_page');

        $allocations->getCollection()->transform(function (LeaveBalance $allocation) {
            $allocation->display_status = $this->allocationStatus($allocation);

            return $allocation;
        });

        $deletionAudits = LeaveAllocationDeletionAudit::query()
            ->orderByDesc('deleted_at')
            ->paginate(15, ['*'], 'deletions_page');

        return view(
            'admin.leave-allocations.index',
            compact('allocations', 'deletionAudits')
        );
    }

    /**
     * Display the special leave allocation form.
     */
    public function create(): View
    {
        $employees = Employee::query()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->sortBy(
                fn(Employee $employee) => strtolower(
                    $employee->user?->name ?? ''
                )
            )
            ->values();

        $leaveTypes = LeaveType::query()
            ->whereIn('code', ['MT', 'PT'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'admin.leave-allocations.create',
            [
                'employees' => $employees,
                'leaveTypes' => $leaveTypes,
                'grantKey' => (string) Str::uuid(),
            ]
        );
    }

    /**
     * Validate and save a special leave allocation.
     */
    public function store(
        Request $request,
        SpecialLeaveAllocationService $allocationService
    ): RedirectResponse {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('is_active', true),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leavetypes', 'id')
                    ->where('is_active', true)
                    ->whereIn('code', ['MT', 'PT']),
            ],
            'days' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:999999.99',
            ],
            'valid_from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'valid_until' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:valid_from',
                'after_or_equal:today',
            ],
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
            'grant_key' => [
                'required',
                'uuid',
            ],
        ]);

        $employee = Employee::query()
            ->findOrFail($validated['employee_id']);

        $leaveType = LeaveType::query()
            ->findOrFail($validated['leave_type_id']);

        try {
            $balance = $allocationService->allocate(
                employee: $employee,
                leaveType: $leaveType,
                days: number_format(
                    (float) $validated['days'],
                    2,
                    '.',
                    ''
                ),
                validFrom: $validated['valid_from'],
                validUntil: $validated['valid_until'],
                reason: $validated['reason'],
                grantKey: $validated['grant_key'],
                admin: $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'allocation' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.leave-allocations.index')
            ->with(
                'success',
                sprintf(
                    '%s allocation saved successfully for %s.',
                    $leaveType->code,
                    $employee->user?->name ?? 'the selected employee'
                )
            );
    }
    /**
     * Determine the display status of a special allocation.
     */
    private function allocationStatus(LeaveBalance $allocation): string
    {
        $today = Carbon::today();

        if (
            $allocation->expires_at !== null
            && $allocation->expires_at->lt($today)
        ) {
            return 'Expired';
        }

        if ($today->lt($allocation->period_start)) {
            return 'Upcoming';
        }

        if ((float) $allocation->remaining_days <= 0) {
            return 'Fully Used';
        }

        if (
            (float) $allocation->remaining_days
            < (float) $allocation->allocated_days
        ) {
            return 'Partially Used';
        }

        return 'Active';
    }

    /**
     * Delete an eligible unused special allocation.
     */
    public function destroy(
        Request $request,
        LeaveBalance $leaveBalance,
        SpecialLeaveAllocationService $allocationService
    ): RedirectResponse {
        // Deletion requires only a reason.
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $allocationService->delete(
                balanceId: (int) $leaveBalance->id,
                reason: $validated['reason'],
                actor: $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'allocation' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.leave-allocations.index')
            ->with(
                'success',
                'The special leave allocation was deleted and its history was preserved.'
            );
    }
}
