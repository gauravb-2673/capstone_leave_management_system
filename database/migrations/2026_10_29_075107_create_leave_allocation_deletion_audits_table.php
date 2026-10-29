<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_allocation_deletion_audits', function (Blueprint $table) {
            $table->id();

            /*
             * Store the original allocation ID as historical data.
             * Deliberately do not add a foreign key to leavebalances,
             * because the original allocation will be hard-deleted.
             */
            $table->unsignedBigInteger('original_balance_id');

            /*
             * Preserve employee and leave-type details even if
             * their original records are later changed or removed.
             */
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('employee_code', 50)->nullable();
            $table->string('employee_name')->nullable();

            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->string('leave_type_code', 10);
            $table->string('leave_type_name');

            $table->string('grant_key')->nullable();

            $table->decimal('allocated_days', 8, 2);
            $table->decimal('remaining_days', 8, 2);

            $table->date('valid_from');
            $table->date('valid_until');

            $table->text('deletion_reason');

            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('deleted_at');

            $table->timestamps();

            $table->index(
                ['employee_id', 'leave_type_code'],
                'leave_alloc_del_employee_type_idx'
            );

            $table->index('original_balance_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_allocation_deletion_audits');
    }
};
