<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_balance_id')
                ->constrained('leavebalances')
                ->restrictOnDelete();

            $table->foreignId('leave_application_id')
                ->nullable()
                ->constrained('leaveapplications')
                ->restrictOnDelete();

            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->constrained('leave_balance_transactions')
                ->restrictOnDelete();

            $table->string('transaction_type', 30);

            $table->decimal('days', 8, 2);

            $table->decimal('balance_after', 8, 2);

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['leave_balance_id', 'created_at']);
            $table->index(
                ['leave_application_id', 'transaction_type'],
                'leave_tx_application_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_transactions');
    }
};
