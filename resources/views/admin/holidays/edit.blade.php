<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Holiday
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">

                <form action="{{ route('admin.holidays.update', $holiday) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="holiday_date" class="block font-medium text-sm text-gray-700">
                            Holiday Date
                        </label>

                        <input type="date" id="holiday_date" name="holiday_date"
                            value="{{ old('holiday_date', $holiday->holiday_date->format('Y-m-d')) }}" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">

                        @error('holiday_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="name" class="block font-medium text-sm text-gray-700">
                            Holiday Name
                        </label>

                        <input type="text" id="name" name="name" value="{{ old('name', $holiday->name) }}"
                            maxlength="255" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">

                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block font-medium text-sm text-gray-700">
                            Description (Optional)
                        </label>

                        <textarea id="description" name="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $holiday->description) }}</textarea>

                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Update Holiday
                        </button>

                        <a href="{{ route('admin.holidays.index') }}"
                            class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
