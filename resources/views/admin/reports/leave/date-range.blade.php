<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Report Information --}}
            <section class="rounded-xl border border-purple-200 bg-white shadow-sm">
                <div class="border-b border-purple-100 bg-purple-50 px-6 py-4">
                    <h1 class="text-lg font-semibold text-purple-900">
                        Select Report Period
                    </h1>

                    <p class="mt-1 text-sm text-purple-700">
                        {{ $description }}
                    </p>
                </div>

                <div class="p-6">
                    <form method="GET" action="{{ $action }}" class="space-y-6">

                        {{-- Start Date --}}
                        <div>
                            <label for="start_date" class="mb-2 block text-sm font-medium text-gray-700">
                                Start Date
                            </label>

                            <input type="date" id="start_date" name="start_date"
                                value="{{ old('start_date', $defaultStartDate) }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">

                            @error('start_date')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- End Date --}}
                        <div>
                            <label for="end_date" class="mb-2 block text-sm font-medium text-gray-700">
                                End Date
                            </label>

                            <input type="date" id="end_date" name="end_date"
                                value="{{ old('end_date', $defaultEndDate) }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">

                            @error('end_date')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-wrap gap-3">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-purple-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-purple-700">
                                Download Excel
                            </button>

                            <a href="{{ route('dashboard') }}"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                Back to Dashboard
                            </a>
                        </div>

                    </form>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
