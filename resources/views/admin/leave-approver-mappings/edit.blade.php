<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Leave Approver Mapping
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

                <form method="POST"
                      action="{{ route('admin.leave-approver-mappings.update', $leaveApproverMapping) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="employee_id" class="block font-medium text-sm text-gray-700">
                            Employee
                        </label>

                        <select id="employee_id" name="employee_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    @selected((string) old('employee_id', $leaveApproverMapping->employee_id) === (string) $employee->id)>
                                    {{ $employee->employee_code }} — {{ $employee->user->name }}
                                </option>
                            @endforeach
                        </select>

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
                            @foreach ($approvers as $approver)
                                <option value="{{ $approver->id }}"
                                    @selected((string) old('approver_user_id', $leaveApproverMapping->approver_user_id) === (string) $approver->id)>
                                    {{ $approver->name }} — {{ $approver->email }} ({{ strtoupper($approver->role) }})
                                </option>
                            @endforeach
                        </select>

                        @error('approver_user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Update Mapping
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
