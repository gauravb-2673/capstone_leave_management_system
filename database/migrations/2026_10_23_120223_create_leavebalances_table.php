<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leavebalances', function (Blueprint $table) {
            $table->id();

            // Employee receiving this allocation.
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            // Leave category, such as CL, SL or LWP.
            $table->foreignId('leave_type_id')
                ->constrained('leavetypes')
                ->restrictOnDelete();

            // Examples: annual, carry_forward, special, imported, event.
            $table->string('allocation_type', 30);

            // Original number of days granted.
            $table->decimal('allocated_days', 8, 2);

            // Number of days still available from this allocation.
            $table->decimal('remaining_days', 8, 2);

            // Service or entitlement period covered by this allocation.
            $table->date('period_start');
            $table->date('period_end');

            // Optional expiry date, particularly useful for carry-forward.
            $table->date('expires_at')->nullable();

            // Links a derived allocation, such as carry-forward,
            // to the original allocation.
            $table->foreignId('source_balance_id')
                ->nullable()
                ->constrained('leavebalances')
                ->restrictOnDelete();

            // Unique key that prevents duplicate grants.
            $table->string('grant_key', 191)->unique();

            $table->timestamps();

            // Helps retrieve an employee's allocations for a leave type.
            $table->index(
                ['employee_id', 'leave_type_id'],
                'leavebalances_employee_leave_type_index'
            );

            // Helps find allocations belonging to a particular period.
            $table->index(
                ['employee_id', 'period_start', 'period_end'],
                'leavebalances_employee_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leavebalances');
    }
};
// | id | employee_id | leave_type_id | allocation_type | allocated_days | remaining_days |
// |---:|---:|---:|---|---:|---:|
// | 1 | 1 | 1 | annual | 12.00 | 10.00 |
// | 2 | 1 | 2 | annual | 6.00 | 4.00 |
// | 3 | 1 | 1 | carry_forward | 3.00 | 3.00 |



// allocation types

// | Value | Meaning |
// |---|---|
// | `annual` | Regular annual entitlement |
// | `carry_forward` | Eligible leave carried from a previous allocation |
// | `special` | Specially granted leave |
// | `imported` | Leave imported from another system |
// | `event` | Allocation created in response to a particular event |


// | Column | Purpose |
// |---|---|
// | `allocated_days` | Original grant |
// | `remaining_days` | Unused portion of that allocation |


// period_start

// Stores the beginning of the period to which the allocation belongs.
// For employee EMP001, whose joining date is 15 January 2025, the second service year starts on:2026-01-15


// period_end
// Stores the end of the allocation's applicable period.
// For the same service year:.  2027-01-14


// period_start = 2026-01-15
// period_end   = 2027-01-14


// The optional expiry date
// Optional expiry date, particularly useful for carry-forward.
// $table->date('expires_at')->nullable();


// 3. See how source_balance_id works
// Suppose we create the new carry-forward record.

// The table now looks like this:
// id	employee_id	leave_type_id	allocation_type	allocated_days	remaining_days	source_balance_id
// 15	1	1	annual	12.00	4.00	NULL
// 28	1	1	carry_forward	4.00	4.00	15


// Let's examine these records.
// Record 15: Original allocation
// id = 15
// allocation_type = annual
// allocated_days = 12.00
// remaining_days = 4.00
// source_balance_id = NULL

// This is the original annual allocation.

// Its source_balance_id is NULL because it was not derived from another balance.
// Record 28: Carry-forward allocation
// id = 28
// allocation_type = carry_forward
// allocated_days = 4.00
// remaining_days = 4.00
// source_balance_id = 15

// This record represents the four days carried forward from balance ID 15.

// The important part is:
// source_balance_id = 15

// It means:

// "The allocation in record 28 was derived from the allocation in record 15."

// We can visualize the relationship like this:
// leavebalances table
// ┌────────────────────────────────────┐
// │ Record ID: 15                      │
// │ Type: annual                       │
// │ Originally allocated: 12 days      │
// │ Remaining: 4 days                  │
// │ Source: NULL                       │
// └─────────────────┬──────────────────┘
//                   │
//                   │ source_balance_id = 15
//                   │
//                   ▼
// ┌────────────────────────────────────┐
// │ Record ID: 28                      │
// │ Type: carry_forward                 │
// │ Originally allocated: 4 days       │
// │ Remaining: 4 days                  │
// │ Source: 15                         │
// └────────────────────────────────────┘

// This is called a self-referencing relationship because a record in a table refers to another record in that same table.
// 4. Why not just store source_balance_id as a normal integer?
// Consider this definition:
// $table->unsignedBigInteger('source_balance_id')->nullable();

// It creates a nullable unsigned big integer column, but it does not establish a foreign-key constraint.
