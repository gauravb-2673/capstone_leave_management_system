<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the departments table.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // Department names must be unique.
            $table->string('department_name')->unique();

            $table->timestamps();
        });
    }

    /**
     * Remove the departments table during rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
