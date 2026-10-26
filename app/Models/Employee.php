<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\LeaveApplication;
use App\Models\LeaveApproverMapping;
use App\Models\LeaveBalance;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_code',
        'joining_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * An employee belongs to one login account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * An employee may belong to one department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function approverMappings(): HasMany
    {
        return $this->hasMany(LeaveApproverMapping::class);
    }
}
