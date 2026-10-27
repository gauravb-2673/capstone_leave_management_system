<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApproverMapping;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveApproverMappingController extends Controller
{
    /**
     * Display all employee-to-approver mappings.
     */
    public function index(): View
    {
        $mappings = LeaveApproverMapping::with([
            'employee.user',
            'approver',
        ])
            ->latest()
            ->paginate(10);

        return view('admin.leave-approver-mappings.index', compact(
            'mappings'
        ));
    }

    /**
     * Display the form for creating a mapping.
     */
    public function create(): View
    {
        $employees = Employee::with('user')
            ->where('is_active', true)
            ->orderBy('employee_code')
            ->get();

        $approvers = User::whereIn('role', ['admin', 'hr'])
            ->orderBy('name')
            ->get();

        return view('admin.leave-approver-mappings.create', compact(
            'employees',
            'approvers'
        ));
    }

    /**
     * Store a new mapping.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where(fn(Builder $query) => $query->where(
                        'is_active',
                        true
                    )),
            ],
            'approver_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn(Builder $query) => $query->whereIn(
                        'role',
                        ['admin', 'hr']
                    )),
                Rule::unique(
                    'leaveapprovermappings',
                    'approver_user_id'
                )->where(fn(Builder $query) => $query->where(
                    'employee_id',
                    $request->input('employee_id')
                )),
            ],
        ]);

        LeaveApproverMapping::create([
            'employee_id' => $validated['employee_id'],
            'approver_user_id' => $validated['approver_user_id'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.leave-approver-mappings.index')
            ->with('success', 'Approver mapping created successfully.');
    }

    /**
     * Display the form for editing a mapping.
     */
    public function edit(
        LeaveApproverMapping $leaveApproverMapping
    ): View {
        $employees = Employee::with('user')
            ->orderBy('employee_code')
            ->get();

        $approvers = User::whereIn('role', ['admin', 'hr'])
            ->orderBy('name')
            ->get();

        return view('admin.leave-approver-mappings.edit', compact(
            'leaveApproverMapping',
            'employees',
            'approvers'
        ));
    }

    /**
     * Update an existing mapping.
     */
    public function update(
        Request $request,
        LeaveApproverMapping $leaveApproverMapping
    ): RedirectResponse {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id'),
            ],
            'approver_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn(Builder $query) => $query->whereIn(
                        'role',
                        ['admin', 'hr']
                    )),
                Rule::unique(
                    'leaveapprovermappings',
                    'approver_user_id'
                )
                    ->where(fn(Builder $query) => $query->where(
                        'employee_id',
                        $request->input('employee_id')
                    ))
                    ->ignore($leaveApproverMapping->id),
            ],
        ]);

        $leaveApproverMapping->update($validated);

        return redirect()
            ->route('admin.leave-approver-mappings.index')
            ->with('success', 'Approver mapping updated successfully.');
    }

    /**
     * Activate or deactivate a mapping.
     */
    public function toggleStatus(
        LeaveApproverMapping $leaveApproverMapping
    ): RedirectResponse {
        $leaveApproverMapping->update([
            'is_active' => ! $leaveApproverMapping->is_active,
        ]);

        return redirect()
            ->route('admin.leave-approver-mappings.index')
            ->with(
                'success',
                'Approver mapping status updated successfully.'
            );
    }
}
