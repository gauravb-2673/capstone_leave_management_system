<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    use HasFactory;

    // Explicitly use the table name created by our migration.
    protected $table = 'leavebalances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'allocation_type',
        'allocated_days',
        'remaining_days',
        'period_start',
        'period_end',
        'expires_at',
        'source_balance_id',
        'grant_key',
    ];

    protected function casts(): array
    {
        return [
            'allocated_days' => 'decimal:2',
            'remaining_days' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(
            LeaveType::class,
            'leave_type_id'
        );
    }

    public function sourceBalance(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'source_balance_id'
        );
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class, 'leave_balance_id');
    }
}
