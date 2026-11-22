<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Set for fixed mock exams; used for "better than X% of students".
            $table->foreignId('mock_id')->nullable()->after('chapter_id')->constrained()->nullOnDelete();
            $table->index(['mock_id', 'user_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex(['mock_id', 'user_id', 'finished_at']);
            $table->dropConstrainedForeignId('mock_id');
        });
    }
};
