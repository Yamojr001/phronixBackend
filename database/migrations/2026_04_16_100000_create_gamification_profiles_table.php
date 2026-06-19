<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->unique();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->unsignedInteger('total_focus_minutes')->default(0);
            $table->unsignedInteger('tests_completed')->default(0);
            $table->unsignedInteger('perfect_scores_count')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->timestamps();

            $table->index(['xp', 'level'], 'gamification_profiles_xp_level_idx');
            $table->index('total_focus_minutes', 'gamification_profiles_minutes_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_profiles');
    }
};
