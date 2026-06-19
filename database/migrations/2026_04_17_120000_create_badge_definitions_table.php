<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('tier'); // Reader, Master, Grand Master, Legendary Genius
            $table->unsignedSmallInteger('tier_level'); // I-VI for Reader, I-V for Master, etc.
            $table->text('description')->nullable();
            $table->string('icon', 64)->default('fa-badge');
            $table->string('color_class')->default('bg-blue-100 text-blue-600');
            $table->unsignedInteger('xp_required')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tier', 'tier_level']);
            $table->index('xp_required');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_definitions');
    }
};
