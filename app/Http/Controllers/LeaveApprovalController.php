<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplicationApproval;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveApprovalController extends Controller
{
    /**
     * Display pending leave applications assigned to the logged-in approver.
     */
    public function index(Request $request): View
    {
        $approvals = LeaveApplicationApproval::query()
            // Only approval tasks that are still pending.
            ->where('status', 'pending')

            // The associated leave application must also be pending.
            ->whereHas('leaveApplication', function ($query) {
                $query->where('status', 'pending');
            })

            // The logged-in user must own the active approver mapping.
            ->whereHas('approverMapping', function ($query) use ($request) {
                $query
                    ->where('approver_user_id', $request->user()->id)
                    ->where('is_active', true);
            })

            // Load related records efficiently for the Blade view.
            ->with([
                'leaveApplication.employee.user',
                'leaveApplication.leaveType',
                'leaveApplication.duration',
            ])

            // Show older pending requests first.
            ->orderBy('created_at')
            ->get();

        return view('leave-approvals.index', [
            'approvals' => $approvals,
        ]);
    }

    /**
     * Approve a pending leave application.
     */
    public function approve(
        Request $request,
        LeaveApplicationApproval $approval,
        LeaveApprovalService $approvalService
    ): RedirectResponse {
        $approvalService->approve(
            $approval,
            $request->user()
        );

        return redirect()
            ->route('leave-approvals.index')
            ->with('success', 'Leave application approved successfully.');
    }

    /**
     * Reject a pending leave application.
     */
    public function reject(
        Request $request,
        LeaveApplicationApproval $approval,
        LeaveApprovalService $approvalService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $approvalService->reject(
            $approval,
            $request->user(),
            $validated['reason']
        );

        return redirect()
            ->route('leave-approvals.index')
            ->with('success', 'Leave application rejected successfully.');
    }
}
