<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stable ids from the content files, so importing again updates instead of duplicating.
        Schema::table('questions', function (Blueprint $table) {
            $table->string('uid', 64)->nullable()->unique()->after('id');
            // Hash of everything a student sees; unchanged hash = nothing to update.
            $table->string('content_hash', 64)->nullable()->after('version');
        });

        foreach (['solved_exercises', 'flashcards', 'passages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('uid', 64)->nullable()->unique()->after('id');
            });
        }

        // The quality gate's "second, independent solve" of each question version.
        Schema::create('question_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_version');
            // Staff member, or null for the independent reviewer agent.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name', 64);
            $table->json('response');
            $table->boolean('matches_key');
            $table->text('note')->nullable();
            $table->timestamps();

            // Named by hand: the automatic name is longer than MySQL's 64-character limit.
            $table->unique(['question_id', 'question_version', 'reviewer_name'], 'question_reviews_version_reviewer_unique');
        });

        // "Report a problem" from students.
        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_version');
            $table->string('reason', 24);
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'user_id', 'question_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_reports');
        Schema::dropIfExists('question_reviews');

        foreach (['solved_exercises', 'flashcards', 'passages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['uid']);
                $table->dropColumn('uid');
            });
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['uid']);
            $table->dropColumn(['uid', 'content_hash']);
        });
    }
};
