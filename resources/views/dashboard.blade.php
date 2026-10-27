<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}

                    @if (auth()->user()->isAdmin())
                        <div class="mt-6 flex flex-wrap gap-3">

                            {{-- Manage Employees --}}
                            <a href="{{ route('admin.employees.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                Manage Employees
                            </a>

                            {{-- Leave Approver Mapping Master --}}
                            <a href="{{ route('admin.leave-approver-mappings.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                Leave Approver Mappings
                            </a>

                            {{-- Holiday Master --}}
                            <a href="{{ route('admin.holidays.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                Holiday Master
                            </a>

                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
