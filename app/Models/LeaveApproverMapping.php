<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApproverMapping extends Model
{
    use HasFactory;

    protected $table = 'leaveapprovermappings';

    protected $fillable = [
        'employee_id',
        'approver_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approvalTasks(): HasMany
    {
        return $this->hasMany(
            LeaveApplicationApproval::class,
            'leave_approver_id'
        );
    }
}
