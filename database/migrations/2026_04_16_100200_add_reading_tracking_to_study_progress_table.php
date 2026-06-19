<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_progress', function (Blueprint $table) {
            $table->unsignedInteger('reading_minutes')->default(0)->after('completed_tasks');
            $table->boolean('reading_completed')->default(false)->after('reading_minutes');
            $table->timestamp('reading_completed_at')->nullable()->after('reading_completed');

            $table->index(['reading_completed', 'reading_completed_at'], 'study_progress_reading_idx');
        });
    }

    public function down(): void
    {
        Schema::table('study_progress', function (Blueprint $table) {
            $table->dropIndex('study_progress_reading_idx');
            $table->dropColumn(['reading_minutes', 'reading_completed', 'reading_completed_at']);
        });
    }
};