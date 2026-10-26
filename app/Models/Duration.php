<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Duration extends Model
{
    use HasFactory;

    protected $table = 'durations';

    protected $fillable = [
        'name',
        'days',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'decimal:2',
        ];
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
