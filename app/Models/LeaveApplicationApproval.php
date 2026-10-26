<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplicationApproval extends Model
{
    use HasFactory;

    protected $table = 'leaveapplicationapprovals';

    protected $fillable = [
        'leave_application_id',
        'leave_approver_id',
        'status',
        'decision_reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(
            LeaveApplication::class,
            'leave_application_id'
        );
    }

    public function approverMapping(): BelongsTo
    {
        return $this->belongsTo(
            LeaveApproverMapping::class,
            'leave_approver_id'
        );
    }
}
