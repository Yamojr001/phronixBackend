<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->onDelete('cascade');
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->onDelete('cascade');
            $table->text('user_answer');
            $table->boolean('is_correct')->default(false);
            $table->json('ai_feedback')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->timestamps();

            $table->index(['quiz_attempt_id', 'quiz_question_id']);
            $table->index('is_correct');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_submissions');
    }
};
