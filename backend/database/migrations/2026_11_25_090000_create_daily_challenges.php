<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The question list of each day's challenge, saved the first time it is asked for,
        // so it stays the same all day even when content changes.
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // Grade and stream, e.g. "g12-natural" or "g10-all".
            $table->string('key', 16);
            $table->json('question_ids');
            $table->timestamps();

            $table->unique(['date', 'key']);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->date('challenge_date')->nullable()->after('mock_id');
            $table->string('challenge_key', 16)->nullable()->after('challenge_date');
            $table->index(['challenge_date', 'challenge_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex(['challenge_date', 'challenge_key', 'user_id']);
            $table->dropColumn(['challenge_date', 'challenge_key']);
        });
        Schema::dropIfExists('daily_challenges');
    }
};
