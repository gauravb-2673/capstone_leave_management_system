<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaveapprovermappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->foreignId('approver_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['employee_id', 'approver_user_id'],
                'leave_approver_employee_user_unique'
            );

            $table->index(['employee_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaveapprovermappings');
    }
};
