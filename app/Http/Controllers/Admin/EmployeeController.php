<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display registered users and their employee-profile status.
     */
    public function index(): View
    {
        $users = User::with('employee')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.employees.index', compact('users'));
    }

    /**
     * Show the form for linking an existing user to an employee profile.
     */
    public function create(): View
    {
        $users = User::whereDoesntHave('employee')
            ->orderBy('name')
            ->get();

        $departments = Department::orderBy('department_name')
            ->get();

        $roles = ['employee', 'admin', 'hr', 'user'];

        return view('admin.employees.create', compact(
            'users',
            'departments',
            'roles'
        ));
    }

    /**
     * Link an existing user to a new employee profile and assign a role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('employees', 'user_id'),
            ],
            'role' => [
                'required',
                Rule::in(['employee', 'admin', 'hr', 'user']),
            ],
            'employee_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employees', 'employee_code'),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'joining_date' => [
                'required',
                'date',
            ],
            'is_active' => [
                'sometimes',
                'accepted',
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::findOrFail($validated['user_id']);

            // Create the employee profile using the existing user account.
            $user->employee()->create([
                'department_id' => $validated['department_id'] ?? null,
                'employee_code' => $validated['employee_code'],
                'joining_date' => $validated['joining_date'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            // Assign the role separately because role is not mass assignable.
            $user->role = $validated['role'];
            $user->save();
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee profile created and user role assigned successfully.');
    }
}
