<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Assign Leave Approver
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.leave-approver-mappings.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="employee_id" class="block font-medium text-sm text-gray-700">
                            Employee
                        </label>

                        <select id="employee_id" name="employee_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select an employee</option>

                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                    {{ $employee->employee_code }} — {{ $employee->user->name }}
                                </option>
                            @endforeach
                        </select>

                        @if ($employees->isEmpty())
                            <p class="mt-1 text-sm text-red-600">
                                No active employees are available. Create or activate an employee profile first.
                            </p>
                        @endif

                        @error('employee_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="approver_user_id" class="block font-medium text-sm text-gray-700">
                            Approver
                        </label>

                        <select id="approver_user_id" name="approver_user_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select an approver</option>

                            @foreach ($approvers as $approver)
                                @if (
                                    (int) old('employee_id') !== 0 &&
                                        (int) $approver->id === (int) $employees->firstWhere('id', old('employee_id'))?->user_id)
                                    @continue
                                @endif

                                <option value="{{ $approver->id }}" @selected((string) old('approver_user_id') === (string) $approver->id)>
                                    {{ $approver->name }} — {{ $approver->email }} ({{ strtoupper($approver->role) }})
                                </option>
                            @endforeach
                        </select>

                        @if ($approvers->isEmpty())
                            <p class="mt-1 text-sm text-red-600">
                                No users with the admin or hr role are available.
                            </p>
                        @endif

                        @error('approver_user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" @disabled($employees->isEmpty() || $approvers->isEmpty())
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            Save Mapping
                        </button>

                        <a href="{{ route('admin.leave-approver-mappings.index') }}"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
