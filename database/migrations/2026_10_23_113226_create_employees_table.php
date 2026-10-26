<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the employees table.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Link the employee profile to a login account.
            // Each user can have at most one employee profile.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Department assignment is optional.
            // If a department is deleted, retain the employee profile.
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // Company-specific employee identifier.
            $table->string('employee_code')->unique();

            // Employment details.
            $table->date('joining_date');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Remove the employees table during rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
