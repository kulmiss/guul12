<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova upgrade (DECISIONS #32, #33, #35): payment requests, nickname reports, a leaderboard block and branding history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set by staff after a bad nickname; the student keeps studying but is hidden from the leaderboard.
            $table->timestamp('leaderboard_blocked_at')->nullable()->after('nickname');
        });

        // A student paid outside the app; staff note it here, then check and approve (gives Premium) or reject.
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 16);
            $table->string('payment_ref', 128);
            $table->unsignedInteger('amount_birr')->nullable();
            $table->string('note', 255)->nullable();
            // waiting → approved (Premium given) or rejected.
            $table->string('status', 16)->default('waiting');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('premium_grant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // "Report nickname" from the leaderboard or a battle.
        Schema::create('nickname_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            // The nickname as it was when reported (it may change later).
            $table->string('nickname', 32);
            $table->string('reason', 16);
            $table->string('note', 255)->nullable();
            // open → reset (nickname removed) or dismissed (it was fine).
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One report per student for the same nickname.
            $table->unique(['reporter_id', 'reported_user_id', 'nickname'], 'nickname_reports_once');
            $table->index(['status', 'created_at']);
        });

        // Every saved branding; the newest row is the one in use.
        Schema::create('branding_versions', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_versions');
        Schema::dropIfExists('nickname_reports');
        Schema::dropIfExists('payment_requests');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('leaderboard_blocked_at');
        });
    }
};
