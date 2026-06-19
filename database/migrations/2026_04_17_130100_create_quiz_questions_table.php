<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->text('question_text');
            $table->enum('question_type', ['short_answer', 'essay', 'select_best', 'multiple_choice'])->default('short_answer');
            $table->text('correct_answer');
            $table->text('answer_explanation')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->index('quiz_id');
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
