<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->enum('difficulty_level', ['easy', 'medium', 'hard', 'expert'])->default('medium');
            $table->boolean('is_published')->default(false);
            $table->boolean('approved_by_admin')->default(false);
            $table->enum('status', ['draft', 'pending_review', 'approved', 'published', 'archived'])->default('draft');
            $table->json('ai_analysis')->nullable();
            $table->unsignedInteger('total_attempts')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamps();

            $table->index('creator_id');
            $table->index('status');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
