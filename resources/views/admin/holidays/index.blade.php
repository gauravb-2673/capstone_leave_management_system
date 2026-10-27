<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Holiday Master
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">

                @if (session('success'))
                    <div class="mb-4 text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-6 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Holiday List</h3>

                    <a href="{{ route('admin.holidays.create') }}"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Add Holiday
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Holiday Name</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200">
                            @forelse ($holidays as $holiday)
                                <tr>
                                    <td class="px-4 py-3">
                                        {{ $holiday->holiday_date->format('d M Y') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $holiday->name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $holiday->description ?: '—' }}
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.holidays.edit', $holiday) }}"
                                            class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </a>

                                        <form action="{{ route('admin.holidays.destroy', $holiday) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Are you sure you want to delete this holiday?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="ml-3 text-red-600 hover:text-red-900">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                        No holidays found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $holidays->links() }}
                </div>

                <a href="{{ route('dashboard') }}" class="mt-6 inline-block text-indigo-600 hover:underline">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
