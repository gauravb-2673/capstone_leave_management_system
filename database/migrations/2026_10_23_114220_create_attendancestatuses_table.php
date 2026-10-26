<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendancestatuses', function (Blueprint $table) {
            $table->id();

            // Short identifier used by the application.
            $table->string('code', 5)->unique();

            // Human-readable description.
            $table->string('name')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendancestatuses');
    }
};
