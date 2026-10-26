<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    use HasFactory;

    protected $table = 'leave_balance_transactions';

    protected $fillable = [
        'leave_balance_id',
        'leave_application_id',
        'reverses_transaction_id',
        'transaction_type',
        'days',
        'balance_after',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(
            LeaveApplication::class,
            'leave_application_id'
        );
    }

    public function reversesTransaction(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reverses_transaction_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
