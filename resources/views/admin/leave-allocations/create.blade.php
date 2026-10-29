<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Allocate Maternity / Paternity Leave
            </h2>

            <a
                href="{{ route('admin.leave-allocations.index') }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
            >
                View Existing Allocations
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @if ($errors->any())
                <div
                    class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    role="alert"
                >
                    <p class="mb-2 font-semibold">
                        Please correct the following errors:
                    </p>

                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        New Special Leave Allocation
                    </h3>

                    <p class="mt-2 text-sm text-gray-600">
                        Use this form to grant Maternity Leave (MT) or
                        Paternity Leave (PT) to an active employee.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.leave-allocations.store') }}"
                    class="space-y-6"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="grant_key"
                        value="{{ old('grant_key', $grantKey) }}"
                    >

                    <div>
                        <label
                            for="employee_id"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Employee <span class="text-red-600">*</span>
                        </label>

                        <select
                            id="employee_id"
                            name="employee_id"
                            required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select an employee</option>

                            @foreach ($employees as $employee)
                                <option
                                    value="{{ $employee->id }}"
                                    @selected(
                                        (string) old('employee_id') ===
                                        (string) $employee->id
                                    )
                                >
                                    {{ $employee->user?->name ?? 'Unnamed Employee' }}
                                    — {{ $employee->employee_code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label
                            for="leave_type_id"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Leave Type <span class="text-red-600">*</span>
                        </label>

                        <select
                            id="leave_type_id"
                            name="leave_type_id"
                            required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select a leave type</option>

                            @foreach ($leaveTypes as $leaveType)
                                <option
                                    value="{{ $leaveType->id }}"
                                    @selected(
                                        (string) old('leave_type_id') ===
                                        (string) $leaveType->id
                                    )
                                >
                                    {{ $leaveType->code }} — {{ $leaveType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label
                            for="days"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Allocated Days <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="number"
                            id="days"
                            name="days"
                            value="{{ old('days') }}"
                            min="0.01"
                            max="999999.99"
                            step="0.01"
                            required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="For example, 90 or 15.5"
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Enter the number of leave days to grant.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label
                                for="valid_from"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Valid From <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="date"
                                id="valid_from"
                                name="valid_from"
                                value="{{ old('valid_from') }}"
                                required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label
                                for="valid_until"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Valid Until <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="date"
                                id="valid_until"
                                name="valid_until"
                                value="{{ old('valid_until') }}"
                                required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            for="reason"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Reason <span class="text-red-600">*</span>
                        </label>

                        <textarea
                            id="reason"
                            name="reason"
                            rows="4"
                            maxlength="2000"
                            required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Explain why this special allocation is being granted."
                        >{{ old('reason') }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-6">
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Save Allocation
                        </button>

                        <a
                            href="{{ route('admin.leave-allocations.index') }}"
                            class="rounded-md border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
