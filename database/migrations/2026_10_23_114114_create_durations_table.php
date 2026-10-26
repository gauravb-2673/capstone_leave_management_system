<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('durations', function (Blueprint $table) {
            $table->id();

            // Human-readable duration name.
            $table->string('name')->unique();

            // Number of leave/attendance days represented.
            // Examples: Full Day = 1.00, Half Day = 0.50.
            $table->decimal('days', 3, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('durations');
    }
};
