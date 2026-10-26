<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaveapplicationapprovals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_application_id')
                ->constrained('leaveapplications')
                ->restrictOnDelete();

            $table->foreignId('leave_approver_id')
                ->constrained('leaveapprovermappings')
                ->restrictOnDelete();

            $table->string('status', 30)->default('pending');

            $table->text('decision_reason')->nullable();

            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['leave_application_id', 'leave_approver_id'],
                'leave_application_approver_unique'
            );

            $table->index(['leave_approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaveapplicationapprovals');
    }
};
