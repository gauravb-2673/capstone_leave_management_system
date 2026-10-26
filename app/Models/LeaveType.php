<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    // Our existing table is named "leavetypes".
    protected $table = 'leavetypes';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(
            LeaveBalance::class,
            'leave_type_id'
        );
    }
}
