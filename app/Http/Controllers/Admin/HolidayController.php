<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(): View
    {
        $holidays = Holiday::orderBy('holiday_date')->paginate(10);

        return view('admin.holidays.index', compact('holidays'));
    }

    public function create(): View
    {
        return view('admin.holidays.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        Holiday::create($validated);

        return redirect()
            ->route('admin.holidays.index')
            ->with('success', 'Holiday created successfully.');
    }

    public function edit(Holiday $holiday): View
    {
        return view('admin.holidays.edit', compact('holiday'));
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $validated = $request->validate([
            'holiday_date' => [
                'required',
                'date',
                Rule::unique('holidays', 'holiday_date')->ignore($holiday->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $holiday->update($validated);

        return redirect()
            ->route('admin.holidays.index')
            ->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()
            ->route('admin.holidays.index')
            ->with('success', 'Holiday deleted successfully.');
    }
}
