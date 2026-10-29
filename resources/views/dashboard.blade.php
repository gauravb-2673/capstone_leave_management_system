<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- Welcome Section --}}
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Welcome, {{ auth()->user()->name }}!
                </h1>

                <p class="mt-2 text-sm text-gray-600">
                    Access your leave management tools from the sections below.
                </p>
            </div>

            {{-- Employee Portal --}}
            @if (auth()->user()->employee)
                <section class="overflow-hidden rounded-xl border border-green-200 bg-white shadow-sm">
                    <div class="border-b border-green-100 bg-green-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-green-900">
                            Employee Portal
                        </h3>

                        <p class="mt-1 text-sm text-green-700">
                            Apply for leave and review your leave application history.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 p-6">
                        <a href="{{ route('leave-applications.create') }}"
                            class="inline-flex items-center justify-center rounded-md bg-green-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-green-700">
                            Apply for Leave
                        </a>

                        <a href="{{ route('leave-applications.index') }}"
                            class="inline-flex items-center justify-center rounded-md border border-green-600 bg-white px-5 py-3 text-sm font-semibold text-green-700 transition hover:bg-green-50">
                            My Leave Applications
                        </a>
                    </div>
                </section>
            @endif

            {{-- Leave Approvals --}}
            <section class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
                <div class="border-b border-blue-100 bg-blue-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-blue-900">
                        Leave Approvals
                    </h3>

                    <p class="mt-1 text-sm text-blue-700">
                        Review and process leave applications assigned to you.
                    </p>
                </div>

                <div class="p-6">
                    <a href="{{ route('leave-approvals.index') }}"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Open Leave Approvals
                    </a>
                </div>
            </section>

            {{-- Administration --}}
            @if (auth()->user()->isAdmin() || auth()->user()->role === 'hr')
                <section class="overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-sm">
                    <div class="border-b border-indigo-100 bg-indigo-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-indigo-900">
                            Administration
                        </h3>

                        <p class="mt-1 text-sm text-indigo-700">
                            Manage employees, leave records, approver assignments, and holidays.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">

                        {{-- Manage Employees --}}
                        <a href="{{ route('admin.employees.index') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-indigo-400 hover:bg-indigo-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-700">
                                Manage Employees
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                View and manage employee records.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-indigo-600">
                                Open Employees →
                            </span>
                        </a>

                        {{-- All Leave Applications --}}
                        <a href="{{ route('admin.leave-applications.index') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-indigo-400 hover:bg-indigo-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-700">
                                All Leave Applications
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                View application history across all employees.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-indigo-600">
                                View Applications →
                            </span>
                        </a>

                        {{-- Leave Approver Mappings --}}
                        <a href="{{ route('admin.leave-approver-mappings.index') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-indigo-400 hover:bg-indigo-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-700">
                                Leave Approver Mappings
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                Configure which approvers handle employee leave requests.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-indigo-600">
                                Manage Mappings →
                            </span>
                        </a>

                        {{-- Holiday Master --}}
                        <a href="{{ route('admin.holidays.index') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-indigo-400 hover:bg-indigo-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-indigo-700">
                                Holiday Master
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                Manage the holiday calendar used by employees.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-indigo-600">
                                Manage Holidays →
                            </span>
                        </a>

                        <a href="{{ route('admin.leave-allocations.index') }}"
                            class="block rounded-lg border border-gray-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50">
                            <h4 class="font-semibold text-gray-900">
                                Special Leave Allocations
                            </h4>

                            <p class="mt-2 text-sm text-gray-600">
                                Allocate and review Maternity Leave (MT) and Paternity Leave (PT).
                            </p>
                        </a>



                    </div>
                </section>
            @endif
            {{-- Reports --}}
            {{-- Administration --}}
            @if (auth()->user()->isAdmin() || auth()->user()->role === 'hr')
                <section class="overflow-hidden rounded-xl border border-purple-200 bg-white shadow-sm">
                    <div class="border-b border-purple-100 bg-purple-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-purple-900">
                            Reports
                        </h3>

                        <p class="mt-1 text-sm text-purple-700">
                            Generate and download leave transaction, balance, and employee leave reports.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">

                        {{-- Monthly Leave Transactions --}}
                        <a href="{{ route('admin.reports.leave.monthly-transactions') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-purple-400 hover:bg-purple-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-purple-700">
                                Monthly Leave Transactions
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                View monthly leave allocations, deductions, reversals, and balance changes.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-purple-600">
                                Download Report →
                            </span>
                        </a>

                        {{-- Employee Leave Balances --}}
                        <a href="{{ route('admin.reports.leave.employee-balances') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-purple-400 hover:bg-purple-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-purple-700">
                                Employee Leave Balances
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                View allocated, used, and remaining leave days for employees.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-purple-600">
                                Download Report →
                            </span>
                        </a>

                        {{-- Monthly Leave Balance Summary --}}
                        <a href="{{ route('admin.reports.leave.monthly-balance-summary') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-purple-400 hover:bg-purple-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-purple-700">
                                Monthly Leave Balance Summary
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                Summarize leave allocations, carry-forwards, deductions, reversals, and expirations.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-purple-600">
                                Download Report →
                            </span>
                        </a>

                        {{-- Monthly Employee Leave Register --}}
                        <a href="{{ route('admin.reports.leave.monthly-register') }}"
                            class="group rounded-lg border border-gray-200 p-4 transition hover:border-purple-400 hover:bg-purple-50">
                            <h4 class="font-semibold text-gray-900 group-hover:text-purple-700">
                                Monthly Employee Leave Register
                            </h4>

                            <p class="mt-1 text-sm text-gray-600">
                                View approved employee leave applications overlapping the selected month.
                            </p>

                            <span class="mt-3 inline-block text-sm font-semibold text-purple-600">
                                Download Report →
                            </span>
                        </a>

                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
