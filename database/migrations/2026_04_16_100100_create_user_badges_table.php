<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('slug', 64);
            $table->string('name');
            $table->string('icon', 64)->default('fa-award');
            $table->text('description')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['user_id', 'slug'], 'user_badges_user_slug_unique');
            $table->index('awarded_at', 'user_badges_awarded_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
