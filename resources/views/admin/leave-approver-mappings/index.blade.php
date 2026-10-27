<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Leave Approver Mappings
            </h2>

            <a href="{{ route('admin.leave-approver-mappings.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                Assign Approver
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if (session('success'))
                    <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Employee Code</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Employee</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Approver</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200">
                            @forelse ($mappings as $mapping)
                                <tr>
                                    <td class="px-4 py-3">
                                        {{ $mapping->employee->employee_code }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $mapping->employee->user->name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $mapping->approver->name }}
                                        <div class="text-sm text-gray-500">
                                            {{ $mapping->approver->email }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($mapping->is_active)
                                            <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-sm">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-sm">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.leave-approver-mappings.edit', $mapping) }}"
                                               class="text-indigo-600 hover:text-indigo-900">
                                                Edit
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('admin.leave-approver-mappings.toggle-status', $mapping) }}">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                        class="{{ $mapping->is_active ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                                    {{ $mapping->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                        No approver mappings found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $mappings->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
