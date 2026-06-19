<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'courses',
            'course_contents',
            'master_timetables',
            'past_questions',
            'reviews',
            'semesters',
            'study_progress',
            'suggestions',
            'system_notifications',
            'tests'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'courses',
            'course_contents',
            'master_timetables',
            'past_questions',
            'reviews',
            'semesters',
            'study_progress',
            'suggestions',
            'system_notifications',
            'tests'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
