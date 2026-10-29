<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            <section class="overflow-hidden rounded-xl border border-purple-200 bg-white shadow-sm">

                <div class="border-b border-purple-100 bg-purple-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-purple-900">
                        Report Filters
                    </h3>

                    <p class="mt-1 text-sm text-purple-700">
                        {{ $description }}
                    </p>
                </div>

                <div class="p-6">

                    <form method="GET" action="{{ $action }}" class="space-y-6">

                        {{-- Distinguishes form submission from opening the page. --}}
                        <input type="hidden" name="generate" value="1">

                        {{-- Date Range Reports --}}
                        @if ($filterType === 'date_range')

                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">
                                    Start Date
                                </label>

                                <input type="date" id="start_date" name="start_date"
                                    value="{{ old('start_date', request('start_date', $defaultStartDate)) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">

                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">
                                    End Date
                                </label>

                                <input type="date" id="end_date" name="end_date"
                                    value="{{ old('end_date', request('end_date', $defaultEndDate)) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">

                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            {{-- Monthly Summary --}}
                        @elseif ($filterType === 'month')
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700">
                                    Select Month
                                </label>

                                <input type="month" id="month" name="month"
                                    value="{{ old('month', request('month', $defaultMonth)) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">

                                @error('month')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                                <p class="mt-1 text-xs text-gray-500">
                                    The report will include transactions created during the selected calendar month.
                                </p>
                            </div>

                            {{-- Employee Leave Balances --}}
                        @elseif ($filterType === 'balances')
                            <div>
                                <label for="employee_id" class="block text-sm font-medium text-gray-700">
                                    Employee
                                </label>

                                <select id="employee_id" name="employee_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">All Employees</option>

                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->getKey() }}" @selected((string) old('employee_id', request('employee_id')) === (string) $employee->getKey())>
                                            {{ $employee->user?->name ?? 'Unknown Employee' }}
                                            @if ($employee->employee_code)
                                                ({{ $employee->employee_code }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('employee_id')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label for="leave_type_id" class="block text-sm font-medium text-gray-700">
                                    Leave Type
                                </label>

                                <select id="leave_type_id" name="leave_type_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">All Leave Types</option>

                                    @foreach ($leaveTypes as $leaveType)
                                        <option value="{{ $leaveType->getKey() }}" @selected((string) old('leave_type_id', request('leave_type_id')) === (string) $leaveType->getKey())>
                                            {{ $leaveType->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('leave_type_id')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <p class="text-xs text-gray-500">
                                Leave balances represent the current recorded balances, not historical balances for a
                                past date.
                            </p>

                        @endif

                        <div class="flex flex-wrap gap-3">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-purple-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-purple-700">
                                Download Excel
                            </button>

                            <a href="{{ route('dashboard') }}"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                Back to Dashboard
                            </a>
                        </div>

                    </form>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
