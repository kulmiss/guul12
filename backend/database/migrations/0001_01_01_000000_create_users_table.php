<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Private student ID used for payments, e.g. ST-7K3Q9. Never shown publicly.
            $table->string('student_id', 16)->unique();
            $table->string('role', 32)->default('student')->index();

            // Staff (Nova) log in with email + password. Students sign in with Google only.
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_sub')->nullable()->unique();

            // Profile (minimum data, used for stats only).
            $table->unsignedTinyInteger('grade')->nullable();
            $table->string('stream', 16)->nullable();
            $table->boolean('is_retaker')->default(false);
            $table->string('region', 64)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('gender', 16)->nullable();
            $table->unsignedSmallInteger('target_score')->nullable();
            $table->time('reminder_time')->nullable();
            $table->string('nickname', 24)->nullable();
            $table->string('avatar_style', 32)->nullable();
            $table->string('avatar_seed', 64)->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('last_active_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
