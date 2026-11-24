<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Shared with friends (the student ID stays private). Made the first time it is needed.
            $table->string('referral_code', 8)->nullable()->unique()->after('student_id');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            // A student can be invited once.
            $table->foreignId('invitee_id')->unique()->constrained('users')->cascadeOnDelete();
            // pending → valid (the friend really studied) or expired (not in time).
            $table->string('status', 16)->default('pending');
            $table->timestamp('valid_at')->nullable();
            $table->foreignId('premium_grant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['inviter_id', 'status', 'valid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
