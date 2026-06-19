<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('spill');
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_options');
    }
};
