<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Admin - Leave Application History') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            {{-- Application history heading --}}
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        All Employee Leave Applications
                    </h3>

                    <p class="mt-1 text-sm text-gray-600">
                        View leave applications and their decision history across all employees.
                    </p>
                </div>
            </div>

            {{-- Application history table --}}
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if ($applications->isEmpty())
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-8 text-center">
                            <p class="font-medium text-gray-700">
                                No leave applications have been submitted yet.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                            Employee
                                        </th>

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
                                            Status / Details
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

                                            $employee = $application->employee;
                                            $user = $employee?->user;
                                        @endphp

                                        <tr>
                                            {{-- Employee --}}
                                            <td class="px-4 py-4 text-sm">
                                                <div class="font-medium text-gray-900">
                                                    {{ $user?->name ?? 'Unknown Employee' }}
                                                </div>

                                                <div class="text-xs text-gray-500">
                                                    {{ $employee?->employee_code ?? 'No employee code' }}
                                                </div>
                                            </td>

                                            {{-- Leave type --}}
                                            <td class="px-4 py-4 text-sm">
                                                <div class="font-medium text-gray-900">
                                                    {{ $application->leaveType?->name ?? 'Unknown' }}
                                                </div>

                                                <div class="text-xs text-gray-500">
                                                    {{ $application->leaveType?->code ?? '' }}
                                                </div>
                                            </td>

                                            {{-- From date --}}
                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->leave_from_date->format('d M Y') }}
                                            </td>

                                            {{-- To date --}}
                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->leave_to_date->format('d M Y') }}
                                            </td>

                                            {{-- Leave days --}}
                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ number_format((float) $application->leavedeductcount, 2) }}
                                            </td>

                                            {{-- Applied date --}}
                                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                                {{ $application->created_at->format('d M Y') }}
                                            </td>

                                            {{-- Status and expandable details --}}
                                            <td class="px-4 py-4 text-sm">
                                                <span
                                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                    {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                                                </span>

                                                <details class="mt-2">
                                                    <summary
                                                        class="cursor-pointer text-indigo-600 hover:text-indigo-800">
                                                        View Details
                                                    </summary>

                                                    <div
                                                        class="mt-2 min-w-64 rounded-md border border-gray-200 bg-gray-50 p-3">
                                                        <p class="font-semibold text-gray-700">
                                                            Reason for Leave
                                                        </p>

                                                        <p class="mt-1 whitespace-pre-line text-gray-600">
                                                            {{ $application->leavereason }}
                                                        </p>

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

                                                    {{-- Approver decision history --}}
                                                    <div class="mt-3 border-t border-gray-200 pt-3">
                                                        <p class="font-semibold text-gray-700">
                                                            Approver History
                                                        </p>

                                                        @forelse ($application->approvals as $approval)
                                                            @php
                                                                $approver = $approval->approverMapping?->approver;

                                                                $approvalStatusClasses = match (
                                                                    strtolower($approval->status)
                                                                ) {
                                                                    'approved' => 'text-green-700',
                                                                    'rejected' => 'text-red-700',
                                                                    'pending' => 'text-yellow-700',
                                                                    'no_longer_required' => 'text-gray-500',
                                                                    default => 'text-gray-700',
                                                                };
                                                            @endphp

                                                            <div class="mt-2 rounded-md bg-white p-3">
                                                                <p class="font-medium text-gray-800">
                                                                    {{ $approver?->name ?? 'Unknown Approver' }}
                                                                </p>

                                                                <p class="text-sm {{ $approvalStatusClasses }}">
                                                                    Decision:
                                                                    {{ ucfirst(str_replace('_', ' ', $approval->status)) }}
                                                                </p>

                                                                @if ($approval->decided_at)
                                                                    <p class="mt-1 text-xs text-gray-500">
                                                                        Decided on:
                                                                        {{ $approval->decided_at->format('d M Y, h:i A') }}
                                                                    </p>
                                                                @endif

                                                                @if ($approval->status === 'rejected' && $approval->decision_reason)
                                                                    <p
                                                                        class="mt-1 whitespace-pre-line text-sm text-red-700">
                                                                        Rejection reason:
                                                                        {{ $approval->decision_reason }}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        @empty
                                                            <p class="mt-1 text-sm text-gray-500">
                                                                No approver records found.
                                                            </p>
                                                        @endforelse
                                                    </div>
                                                </details>

                                                {{-- Admin cancellation: approved applications only --}}
                                                @if ($application->status === 'approved')
                                                    <details class="mt-3">
                                                        <summary
                                                            class="inline-flex cursor-pointer items-center rounded-md bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700">
                                                            Cancel Leave
                                                        </summary>

                                                        <div
                                                            class="mt-3 rounded-md border border-red-200 bg-red-50 p-3">
                                                            <form method="POST"
                                                                action="{{ route('admin.leave-applications.cancel', $application) }}"
                                                                onsubmit="return confirm('Are you sure you want to cancel this approved leave application?');">

                                                                @csrf

                                                                <label
                                                                    for="admin_cancellation_reason_{{ $application->id }}"
                                                                    class="block font-semibold text-red-800">
                                                                    Cancellation Reason
                                                                </label>

                                                                <textarea id="admin_cancellation_reason_{{ $application->id }}" name="cancellation_reason" rows="3"
                                                                    maxlength="1000" required
                                                                    class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                                                    placeholder="Explain why the approved leave is being cancelled."></textarea>

                                                                <button type="submit"
                                                                    class="mt-3 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700">
                                                                    Confirm Cancellation
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </details>
                                                @endif

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
