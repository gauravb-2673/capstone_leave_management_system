<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Leave Approvals') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Success message --}}
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 p-4 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- General validation errors --}}
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-100 p-4 text-red-800">
                    <p class="font-semibold">
                        Please correct the following errors:
                    </p>

                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold">
                            Pending Leave Applications
                        </h3>

                        <p class="mt-1 text-sm text-gray-600">
                            Review applications assigned to you before making
                            an approval decision.
                        </p>
                    </div>

                    @if ($approvals->isEmpty())
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-6 text-center">
                            <p class="font-medium text-gray-700">
                                No pending leave applications.
                            </p>

                            <p class="mt-1 text-sm text-gray-500">
                                Applications assigned to you will appear here.
                            </p>
                        </div>
                    @else
                        <div class="space-y-6">

                            @foreach ($approvals as $approval)
                                @php
                                    $application = $approval->leaveApplication;
                                    $employee = $application->employee;
                                @endphp

                                <div class="rounded-lg border border-gray-200 p-5">

                                    {{-- Employee and application details --}}
                                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                {{ $employee->user?->name ?? 'Unknown employee' }}
                                            </h4>

                                            <p class="text-sm text-gray-500">
                                                Employee Code:
                                                {{ $employee->employee_code }}
                                            </p>
                                        </div>

                                        <span
                                            class="inline-flex rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-800">
                                            Pending
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                                        {{-- Leave type --}}
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Leave Type
                                            </p>

                                            <p class="font-medium">
                                                {{ $application->leaveType->name }}
                                                ({{ $application->leaveType->code }})
                                            </p>
                                        </div>

                                        {{-- Duration --}}
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Duration
                                            </p>

                                            <p class="font-medium">
                                                {{ $application->duration->name }}
                                            </p>
                                        </div>

                                        {{-- Requested days --}}
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Days Requested
                                            </p>

                                            <p class="font-medium">
                                                {{ number_format((float) $application->leavedeductcount, 2) }}
                                            </p>
                                        </div>

                                        {{-- Start date --}}
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                From Date
                                            </p>

                                            <p class="font-medium">
                                                {{ $application->leave_from_date->format('d M Y') }}
                                            </p>
                                        </div>

                                        {{-- End date --}}
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                To Date
                                            </p>

                                            <p class="font-medium">
                                                {{ $application->leave_to_date->format('d M Y') }}
                                            </p>
                                        </div>

                                        {{-- Half-day period --}}
                                        @if ($application->half_day_period)
                                            <div>
                                                <p class="text-sm text-gray-500">
                                                    Half-Day Period
                                                </p>

                                                <p class="font-medium">
                                                    {{ $application->half_day_period }}
                                                </p>
                                            </div>
                                        @endif

                                    </div>

                                    {{-- Reason for leave --}}
                                    <div class="mt-5">
                                        <p class="text-sm font-semibold text-gray-700">
                                            Employees Reason
                                        </p>

                                        <p class="mt-1 whitespace-pre-line text-gray-600">
                                            {{ $application->leavereason }}</p>
                                    </div>

                                    <div class="mt-6 border-t border-gray-200 pt-5">

                                        {{-- Approve form --}}
                                        <form method="POST" action="{{ route('leave-approvals.approve', $approval) }}"
                                            class="mb-6"
                                            onsubmit="return confirm('Are you sure you want to approve this leave application?');">
                                            @csrf

                                            <button type="submit"
                                                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                                Approve
                                            </button>
                                        </form>

                                        {{-- Reject form --}}
                                        <form method="POST" action="{{ route('leave-approvals.reject', $approval) }}"
                                            onsubmit="return confirm('Are you sure you want to reject this leave application?');">
                                            @csrf

                                            <div class="max-w-2xl">
                                                <label for="reason-{{ $approval->id }}"
                                                    class="block text-sm font-medium text-gray-700">
                                                    Rejection Reason
                                                    <span class="text-red-600">*</span>
                                                </label>

                                                <textarea id="reason-{{ $approval->id }}" name="reason" rows="3" maxlength="2000" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="Explain why this leave application is being rejected.">{{ old('reason') }}</textarea>

                                                <p class="mt-1 text-xs text-gray-500">
                                                    A reason is required to reject an application.
                                                </p>
                                            </div>

                                            <button type="submit"
                                                class="mt-3 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                                Reject
                                            </button>
                                        </form>

                                    </div>
                                </div>
                            @endforeach

                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
