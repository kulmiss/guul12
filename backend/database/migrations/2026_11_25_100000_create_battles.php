<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two students answer the same questions, each when they like. No chat, no contact:
        // each sees only the other's nickname, avatar and score.
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            // Told to a friend to join; random battles have one too but nobody needs it.
            $table->string('code', 8)->unique();
            // Grade and stream, like the daily challenge ("g12-natural").
            $table->string('key', 16);
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_random');
            $table->json('question_ids');
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['is_random', 'key', 'opponent_id', 'expires_at']);
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->foreignId('battle_id')->nullable()->after('challenge_key')->constrained()->nullOnDelete();
            $table->index(['battle_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // MySQL: the foreign key uses the index, so it goes first.
            $table->dropForeign(['battle_id']);
            $table->dropIndex(['battle_id', 'user_id']);
            $table->dropColumn('battle_id');
        });
        Schema::dropIfExists('battles');
    }
};
