<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leavetypes', function (Blueprint $table) {
            $table->id();

            // Short code used by the application.
            $table->string('code', 10)->unique();

            // Human-readable leave type.
            $table->string('name');

            // Whether employees can currently apply for this type.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leavetypes');
    }
};
