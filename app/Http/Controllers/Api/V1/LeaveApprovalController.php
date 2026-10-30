<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplicationApproval;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveApprovalController extends Controller
{
    /**
     * Approve a leave application approval task.
     */
    public function approve(
        Request $request,
        int $id,
        LeaveApprovalService $approvalService
    ): JsonResponse {
        // Find the individual approval task.
        $approval = LeaveApplicationApproval::query()
            ->whereKey($id)
            ->first();

        if (! $approval) {
            return response()->json([
                'message' => 'Leave approval task not found.',
            ], 404);
        }

        // The service checks whether this user is the assigned approver
        // and whether the application can still be approved.
        $approvalService->approve(
            $approval,
            $request->user()
        );

        return response()->json([
            'message' => 'Leave application approved successfully.',
        ]);
    }

    /**
     * Reject a leave application approval task.
     */
    public function reject(
        Request $request,
        int $id,
        LeaveApprovalService $approvalService
    ): JsonResponse {
        // Validate the mandatory rejection reason.
        $validated = $request->validate([
            'decision_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        // Find the individual approval task.
        $approval = LeaveApplicationApproval::query()
            ->whereKey($id)
            ->first();

        if (! $approval) {
            return response()->json([
                'message' => 'Leave approval task not found.',
            ], 404);
        }

        // The service checks approver authorization and rejection rules.
        $approvalService->reject(
            $approval,
            $request->user(),
            $validated['decision_reason']
        );

        return response()->json([
            'message' => 'Leave application rejected successfully.',
        ]);
    }
}
