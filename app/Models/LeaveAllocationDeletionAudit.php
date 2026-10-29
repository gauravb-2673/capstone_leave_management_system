<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAllocationDeletionAudit extends Model
{
    protected $table = 'leave_allocation_deletion_audits';

    protected $fillable = [
        'original_balance_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'leave_type_id',
        'leave_type_code',
        'leave_type_name',
        'grant_key',
        'allocated_days',
        'remaining_days',
        'valid_from',
        'valid_until',
        'deletion_reason',
        'deleted_by',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'allocated_days' => 'decimal:2',
            'remaining_days' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
