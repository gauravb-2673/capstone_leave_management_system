<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaveapplications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->foreignId('leave_type_id')
                ->constrained('leavetypes')
                ->restrictOnDelete();

            $table->foreignId('duration_id')
                ->constrained('durations')
                ->restrictOnDelete();

            $table->string('half_day_period', 20)->nullable();

            $table->date('leave_from_date');

            $table->date('leave_to_date');

            $table->decimal('leavedeductcount', 8, 2);

            $table->text('leavereason');

            $table->string('status', 20)->default('pending');

            $table->text('cancellation_reason')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);

            $table->index(
                ['employee_id', 'leave_from_date', 'leave_to_date'],
                'leave_app_employee_dates_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaveapplications');
    }
};
