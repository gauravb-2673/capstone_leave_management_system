<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Services\Leave\LeaveCancellationService;
use Illuminate\Http\RedirectResponse;

class LeaveApplicationHistoryController extends Controller
{
    /**
     * Display leave application history for all employees.
     */
    public function index(Request $request): View
    {
        $query = LeaveApplication::query()
            ->with([
                'employee.user',
                'leaveType',
                'duration',
                'approvals.approverMapping.approver',
            ])
            ->latest();

        $applications = $query
            ->paginate(10)
            ->withQueryString();

        return view('admin.leave-applications.index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Cancel an approved leave application as an admin.
     */
    public function cancel(
        Request $request,
        LeaveApplication $leaveApplication,
        LeaveCancellationService $cancellationService
    ): RedirectResponse {
        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $cancellationService->cancel(
            $leaveApplication,
            $request->user(),
            $validated['cancellation_reason']
        );

        return redirect()
            ->route('admin.leave-applications.index')
            ->with(
                'success',
                'The approved leave application has been cancelled successfully.'
            );
    }
}
