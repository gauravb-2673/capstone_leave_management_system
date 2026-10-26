<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $table = 'leaveapplications';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'duration_id',
        'half_day_period',
        'leave_from_date',
        'leave_to_date',
        'leavedeductcount',
        'leavereason',
        'status',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'leave_from_date' => 'date',
            'leave_to_date' => 'date',
            'leavedeductcount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(Duration::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(
            LeaveApplicationApproval::class,
            'leave_application_id'
        );
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(
            LeaveBalanceTransaction::class,
            'leave_application_id'
        );
    }
}
