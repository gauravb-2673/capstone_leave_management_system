<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Apply for Leave
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">

                {{-- Success message --}}
                @if (session('success'))
                    <div class="mb-4 rounded-md bg-green-100 p-4 text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- General error message --}}
                @if (session('error'))
                    <div class="mb-4 rounded-md bg-red-100 p-4 text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Employee information --}}
                <div class="mb-6 rounded-md bg-gray-50 p-4">
                    <p class="text-sm text-gray-600">Employee Code</p>
                    <p class="font-medium text-gray-900">
                        {{ $employee->employee_code }}
                    </p>
                </div>

                {{-- Active approver warning --}}
                @if (!$hasApprover)
                    <div class="mb-6 rounded-md bg-yellow-100 p-4 text-yellow-800">
                        No active approver is assigned to your employee profile.
                        Please contact the administrator before applying for leave.
                    </div>
                @endif

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div class="mb-6 rounded-md bg-red-100 p-4 text-red-800">
                        <p class="mb-2 font-semibold">
                            Please correct the following errors:
                        </p>

                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('leave-applications.store') }}" class="space-y-6">
                    @csrf

                    {{-- Leave type --}}
                    <div>
                        <label for="leave_type_id" class="mb-1 block font-medium text-gray-700">
                            Leave Type
                        </label>

                        <select id="leave_type_id" name="leave_type_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select leave type</option>

                            @foreach ($leaveTypes as $leaveType)
                                @php
                                    if ($leaveType->code === 'LWP') {
                                        $remainingDays = (float) $lwpBalance['remaining_days'];
                                    } else {
                                        $remainingDays = (float) ($remainingLeaveDays[$leaveType->id] ?? 0);
                                    }
                                @endphp

                                <option value="{{ $leaveType->id }}" @selected(old('leave_type_id') == $leaveType->id)>
                                    {{ $leaveType->name }}
                                    ({{ $leaveType->code }})
                                    — {{ number_format($remainingDays, 2) }} days remaining
                                </option>
                            @endforeach
                        </select>

                        @error('leave_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Duration --}}
                    <div>
                        <label for="duration_id" class="mb-1 block font-medium text-gray-700">
                            Duration
                        </label>

                        <select id="duration_id" name="duration_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select duration</option>

                            @foreach ($durations as $duration)
                                <option value="{{ $duration->id }}" data-duration-name="{{ $duration->name }}"
                                    @selected(old('duration_id') == $duration->id)>
                                    {{ $duration->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('duration_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Half-day period --}}
                    <div id="half_day_container" class="hidden">
                        <label for="half_day_period" class="mb-1 block font-medium text-gray-700">
                            Half-Day Period
                        </label>

                        <select id="half_day_period" name="half_day_period"
                            class="w-full rounded-md border-gray-300 shadow-sm" disabled>
                            <option value="">Select half-day period</option>

                            <option value="first_half" @selected(old('half_day_period') === 'first_half')>
                                First Half
                            </option>

                            <option value="second_half" @selected(old('half_day_period') === 'second_half')>
                                Second Half
                            </option>
                        </select>

                        @error('half_day_period')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Start date --}}
                    <div>
                        <label for="leave_from_date" class="mb-1 block font-medium text-gray-700">
                            From Date
                        </label>

                        <input type="date" id="leave_from_date" name="leave_from_date"
                            value="{{ old('leave_from_date') }}" required
                            class="w-full rounded-md border-gray-300 shadow-sm">

                        @error('leave_from_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- End date --}}
                    <div>
                        <label for="leave_to_date" class="mb-1 block font-medium text-gray-700">
                            To Date
                        </label>

                        <input type="date" id="leave_to_date" name="leave_to_date"
                            value="{{ old('leave_to_date') }}" required
                            class="w-full rounded-md border-gray-300 shadow-sm">

                        @error('leave_to_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Reason --}}
                    <div>
                        <label for="leavereason" class="mb-1 block font-medium text-gray-700">
                            Reason for Leave
                        </label>

                        <textarea id="leavereason" name="leavereason" rows="4" maxlength="5000" required
                            class="w-full rounded-md border-gray-300 shadow-sm" placeholder="Explain why you need leave">{{ old('leavereason') }}</textarea>

                        @error('leavereason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-between">
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                            Back to Dashboard
                        </a>

                        <button type="submit" @disabled(!$hasApprover || $leaveTypes->isEmpty())
                            class="rounded-md bg-indigo-600 px-5 py-2 text-white
                                   hover:bg-indigo-700
                                   disabled:cursor-not-allowed disabled:opacity-50">
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Display the half-day field only for Half Day duration --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const durationSelect = document.getElementById('duration_id');
            const halfDayContainer = document.getElementById('half_day_container');
            const halfDayPeriod = document.getElementById('half_day_period');

            function updateHalfDayField() {
                const selectedOption =
                    durationSelect.options[durationSelect.selectedIndex];

                const isHalfDay =
                    selectedOption &&
                    selectedOption.dataset.durationName === 'Half Day';

                halfDayContainer.classList.toggle('hidden', !isHalfDay);
                halfDayPeriod.disabled = !isHalfDay;
                halfDayPeriod.required = isHalfDay;

                if (!isHalfDay) {
                    halfDayPeriod.value = '';
                }
            }

            durationSelect.addEventListener('change', updateHalfDayField);

            updateHalfDayField();
        });
    </script>
</x-app-layout>
