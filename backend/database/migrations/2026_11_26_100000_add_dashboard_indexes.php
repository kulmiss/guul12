<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the Nova dashboard (counts by date), so the scheduled refresh stays quick on big tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('attempts', fn (Blueprint $table) => $table->index('finished_at'));
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at']);
            $table->index(['role', 'last_active_at']);
        });
        Schema::table('premium_grants', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('battles', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('battles', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('premium_grants', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'last_active_at']);
            $table->dropIndex(['role', 'created_at']);
        });
        Schema::table('attempts', fn (Blueprint $table) => $table->dropIndex(['finished_at']));
        Schema::table('attempt_answers', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
