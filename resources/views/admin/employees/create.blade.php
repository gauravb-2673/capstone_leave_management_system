<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Link User to Employee') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form method="POST" action="{{ route('admin.employees.store') }}">
                        @csrf

                        {{-- Select an existing registered user --}}
                        <div class="mb-4">
                            <label for="user_id" class="block font-medium text-sm text-gray-700">
                                Registered User
                            </label>

                            <select id="user_id" name="user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                <option value="">Select a user</option>

                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>

                            @error('user_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            @if ($users->isEmpty())
                                <p class="mt-1 text-sm text-amber-700">
                                    All registered users already have employee profiles.
                                </p>
                            @endif
                        </div>

                        {{-- Assign or change the user's role --}}
                        <div class="mb-4">
                            <label for="role" class="block font-medium text-sm text-gray-700">
                                Role
                            </label>

                            <select name="role" id="role"
                                class="block w-full rounded-md border-gray-300 shadow-sm" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}" @selected(old('role', 'employee') === $role)>
                                        {{ ucfirst($role) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Employee code --}}
                        <div class="mb-4">
                            <label for="employee_code" class="block font-medium text-sm text-gray-700">
                                Employee Code
                            </label>

                            <input id="employee_code" name="employee_code" type="text"
                                value="{{ old('employee_code') }}" maxlength="255"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>

                            @error('employee_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Department --}}
                        <div class="mb-4">
                            <label for="department_id" class="block font-medium text-sm text-gray-700">
                                Department
                            </label>

                            <select id="department_id" name="department_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">No department</option>

                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                        {{ $department->department_name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('department_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Joining date --}}
                        <div class="mb-4">
                            <label for="joining_date" class="block font-medium text-sm text-gray-700">
                                Joining Date
                            </label>

                            <input id="joining_date" name="joining_date" type="date"
                                value="{{ old('joining_date') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>

                            @error('joining_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Active status --}}
                        <div class="mb-6">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') == '1')
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm">

                                <span class="ms-2 text-sm text-gray-700">
                                    Employee is active
                                </span>
                            </label>

                            @error('is_active')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit" @disabled($users->isEmpty())
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                                Create Employee Profile
                            </button>

                            <a href="{{ route('admin.employees.index') }}"
                                class="text-sm text-gray-600 hover:text-gray-900">
                                Cancel
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
