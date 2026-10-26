<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_name',
    ];

    /**
     * A department can have many employees.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
