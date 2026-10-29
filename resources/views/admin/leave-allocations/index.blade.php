<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Special Leave Allocations
            </h2>

            <a href="{{ route('admin.leave-allocations.create') }}"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + New Allocation
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- Success message --}}
            @if (session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700" role="status">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Validation and operation errors --}}
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                    <p class="mb-2 font-semibold">
                        The operation could not be completed:
                    </p>

                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Current allocations --}}
            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Current and Historical Allocations
                    </h3>

                    <p class="mt-1 text-sm text-gray-600">
                        View allocation balances, validity periods, and deletion eligibility.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Employee
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Leave Type
                                </th>

                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Allocated
                                </th>

                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Remaining
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Valid From
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Valid Until
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Status
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($allocations as $allocation)
                                @php
                                    $allocatedDays = number_format((float) $allocation->allocated_days, 2, '.', '');

                                    $remainingDays = number_format((float) $allocation->remaining_days, 2, '.', '');

                                    $isUnused = $allocatedDays === $remainingDays;

                                    $isExpired = $allocation->display_status === 'Expired';

                                    $canAttemptDeletion = $isUnused && !$isExpired;
                                @endphp

                                <tr>
                                    {{-- Employee --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">
                                            {{ $allocation->employee?->user?->name ?? 'Unknown Employee' }}
                                        </div>

                                        @if ($allocation->employee?->employee_code)
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $allocation->employee->employee_code }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Leave type --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        <span class="font-medium">
                                            {{ $allocation->leaveType?->code }}
                                        </span>

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $allocation->leaveType?->name }}
                                        </div>
                                    </td>

                                    {{-- Allocated days --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                        {{ number_format((float) $allocation->allocated_days, 2) }}
                                    </td>

                                    {{-- Remaining days --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                        {{ number_format((float) $allocation->remaining_days, 2) }}
                                    </td>

                                    {{-- Valid from --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ $allocation->period_start?->format('d M Y') }}
                                    </td>

                                    {{-- Valid until --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ $allocation->period_end?->format('d M Y') }}
                                    </td>

                                    {{-- Status --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $statusClasses = match ($allocation->display_status) {
                                                'Active' => 'bg-green-100 text-green-800',
                                                'Upcoming' => 'bg-blue-100 text-blue-800',
                                                'Partially Used' => 'bg-yellow-100 text-yellow-800',
                                                'Fully Used' => 'bg-gray-100 text-gray-800',
                                                'Expired' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp

                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ $allocation->display_status }}
                                        </span>
                                    </td>

                                    {{-- Delete action --}}
                                    <td class="px-6 py-4 text-sm">
                                        @if ($canAttemptDeletion)
                                            <details class="min-w-56">
                                                <summary
                                                    class="cursor-pointer font-semibold text-red-600 hover:text-red-800">
                                                    Delete allocation
                                                </summary>

                                                <form method="POST"
                                                    action="{{ route('admin.leave-allocations.destroy', $allocation) }}"
                                                    class="mt-3 space-y-3"
                                                    onsubmit="return confirm('Are you sure you want to delete this allocation? Its deletion details will be preserved in the audit history.');">
                                                    @csrf
                                                    @method('DELETE')

                                                    <div>
                                                        <label for="reason-{{ $allocation->id }}"
                                                            class="mb-1 block text-xs font-medium text-gray-700">
                                                            Reason for deletion
                                                        </label>

                                                        <textarea id="reason-{{ $allocation->id }}" name="reason" rows="3" maxlength="2000" required
                                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            placeholder="Explain why this allocation must be deleted.">{{ old('reason') }}</textarea>
                                                    </div>

                                                    <button type="submit"
                                                        class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                                                        Confirm Delete
                                                    </button>
                                                </form>
                                            </details>
                                        @else
                                            <span class="text-xs text-gray-500">
                                                Not eligible for deletion
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No special MT/PT allocations have been created yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($allocations->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $allocations->withQueryString()->links() }}
                    </div>
                @endif
            </section>

            {{-- Deleted allocation audit history --}}
            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Deleted Allocation History
                    </h3>

                    <p class="mt-1 text-sm text-gray-600">
                        This history preserves the details and reason for each deleted allocation.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Employee
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Leave Type
                                </th>

                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Allocated
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Validity
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Deletion Reason
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Deleted By
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Deleted At
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($deletionAudits as $audit)
                                <tr>
                                    {{-- Employee snapshot --}}
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">
                                            {{ $audit->employee_name ?? 'Unknown Employee' }}
                                        </div>

                                        @if ($audit->employee_code)
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $audit->employee_code }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Leave type snapshot --}}
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div class="font-medium">
                                            {{ $audit->leave_type_code }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $audit->leave_type_name }}
                                        </div>
                                    </td>

                                    {{-- Original allocated days --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                        {{ number_format((float) $audit->allocated_days, 2) }}
                                    </td>

                                    {{-- Original validity period --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        <div>
                                            {{ $audit->valid_from?->format('d M Y') }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            to {{ $audit->valid_until?->format('d M Y') }}
                                        </div>
                                    </td>

                                    {{-- Deletion reason --}}
                                    <td class="min-w-64 px-6 py-4 text-sm text-gray-700">
                                        <div class="whitespace-pre-line break-words">
                                            {{ $audit->deletion_reason }}
                                        </div>
                                    </td>

                                    {{-- Actor --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ $audit->deletedBy?->name ?? 'Unknown User' }}
                                    </td>

                                    {{-- Deletion timestamp --}}
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ $audit->deleted_at?->format('d M Y, h:i A') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No allocations have been deleted.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($deletionAudits->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $deletionAudits->withQueryString()->links() }}
                    </div>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
