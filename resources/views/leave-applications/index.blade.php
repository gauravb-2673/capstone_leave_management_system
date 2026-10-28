<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('My Leave Applications') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            {{-- Success message --}}
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 p-4 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Error message --}}
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-100 p-4 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Page heading and Apply button --}}
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Leave Application History
                    </h3>

                    <p class="mt-1 text-sm text-gray-600">
                        View the status and details of your submitted applications.
                    </p>
                </div>

                <a href="{{ route('leave-applications.create') }}"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2
                           text-xs font-semibold uppercase tracking-widest text-white
                           hover:bg-indigo-700">
                    Apply for Leave
                </a>
            </div>

            {{-- Application history --}}
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{-- Success message --}}
                    @if (session('success'))
                        <div class="mb-4 rounded-md border border-green-200 bg-green-50 p-4 text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Validation and service errors --}}
                    @if ($errors->any())
                        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-red-800">
                            <p class="font-semibold">Please correct the following:</p>

                            <ul class="mt-2 list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($applications->isEmpty())
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-8 text-center">
                            <p class="font-medium text-gray-700">
                                You have not submitted any leave applications yet.
                            </p>

                            <p class="mt-2 text-sm text-gray-500">
                                Once you submit an application, it will appear here.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            Leave Type
                                        </th>

                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            From
                                        </th>

                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            To
                                        </th>

                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            Days
                                        </th>

                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            Applied On
                                        </th>

                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($applications as $application)
                                        @php
                                            $status = strtolower($application->status);

                                            $statusClasses = match ($status) {
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'cancelled' => 'bg-gray-200 text-gray-800',
                                                default => 'bg-gray-100 text-gray-700',
                                            };

                                            $rejectedApproval = $application->approvals->firstWhere(
                                                'status',
                                                'rejected',
                                            );
                                        @endphp

                                        <tr>
                                            <td class="px-4 py-4 text-sm">
                                                <div class="font-medium text-gray-900">
                                                    {{ $application->leaveType?->name ?? 'Unknown' }}
                                                </div>

                                                <div class="text-xs text-gray-500">
                                                    {{ $application->leaveType?->code ?? '' }}
                                                </div>
                                            </td>

                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->leave_from_date->format('d M Y') }}
                                            </td>

                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->leave_to_date->format('d M Y') }}
                                            </td>

                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ number_format((float) $application->leavedeductcount, 2) }}
                                            </td>

                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->created_at->format('d M Y') }}
                                            </td>

                                            <td class="px-4 py-4 text-sm">
                                                {{-- Application status --}}
                                                <span
                                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                    {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                                                </span>

                                                {{-- Expandable application details --}}
                                                <details class="mt-2">
                                                    <summary
                                                        class="cursor-pointer text-indigo-600 hover:text-indigo-800">
                                                        View Details
                                                    </summary>

                                                    <div
                                                        class="mt-2 min-w-64 rounded-md border border-gray-200 bg-gray-50 p-3">
                                                        {{-- Leave reason --}}
                                                        <p class="font-semibold text-gray-700">
                                                            Reason for Leave
                                                        </p>

                                                        <p class="mt-1 whitespace-pre-line text-gray-600">
                                                            {{ $application->leavereason }}
                                                        </p>
                                                        {{-- Employee cancellation form: pending applications only --}}
                                                        @if ($application->status === 'pending')
                                                            <form method="POST"
                                                                action="{{ route('leave-applications.cancel', $application) }}"
                                                                class="mt-4 rounded-md border border-red-200 bg-red-50 p-4"
                                                                onsubmit="return confirm('Are you sure you want to cancel this leave application?');">
                                                                @csrf

                                                                <label for="cancellation_reason_{{ $application->id }}"
                                                                    class="block font-semibold text-red-800">
                                                                    Cancellation Reason
                                                                </label>

                                                                <textarea id="cancellation_reason_{{ $application->id }}" name="cancellation_reason" rows="3" maxlength="1000"
                                                                    required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                                                    placeholder="Enter why you are cancelling this application.">{{ old('cancellation_reason') }}</textarea>

                                                                @error('cancellation_reason')
                                                                    <p class="mt-1 text-sm text-red-700">
                                                                        {{ $message }}</p>
                                                                @enderror

                                                                <button type="submit"
                                                                    class="mt-3 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700">
                                                                    Cancel Application
                                                                </button>
                                                            </form>
                                                        @endif
                                                        {{-- Rejection reason --}}
                                                        @if ($rejectedApproval)
                                                            <div
                                                                class="mt-3 rounded-md border border-red-200 bg-red-50 p-3">
                                                                <p class="font-semibold text-red-800">
                                                                    Rejection Reason
                                                                </p>

                                                                <p class="mt-1 whitespace-pre-line text-red-700">
                                                                    {{ $rejectedApproval->decision_reason ?: 'No rejection reason was recorded.' }}
                                                                </p>
                                                            </div>
                                                        @endif

                                                        {{-- Cancellation reason --}}
                                                        @if ($application->status === 'cancelled')
                                                            <div
                                                                class="mt-3 rounded-md border border-gray-200 bg-white p-3">
                                                                <p class="font-semibold text-gray-700">
                                                                    Cancellation Reason
                                                                </p>

                                                                <p class="mt-1 whitespace-pre-line text-gray-600">
                                                                    {{ $application->cancellation_reason ?: 'No cancellation reason was recorded.' }}
                                                                </p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        <div class="mt-6">
                            {{ $applications->links() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
