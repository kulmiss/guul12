<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('key_points');
            $table->json('formulas');
            $table->text('body')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->string('source', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('solved_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->text('problem');
            $table->json('steps');
            $table->text('final_answer');
            $table->string('status', 16)->default('draft')->index();
            $table->string('source', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('term', 191);
            $table->text('definition');
            $table->string('status', 16)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->text('body');
            $table->string('source', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('passage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index();
            $table->text('stem');
            $table->string('media_path', 255)->nullable();
            $table->unsignedTinyInteger('difficulty')->default(3);
            $table->string('status', 16)->default('draft')->index();
            $table->json('answer_key');
            $table->text('explanation');
            $table->string('source', 191)->nullable();
            $table->json('tags')->nullable();
            // Bumped whenever the stem, options or key change; attempts store the version they answered.
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('times_answered')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['chapter_id', 'status']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            // A–F for choices, L1/R1 for matching sides, any key for ordering items.
            $table->string('key', 8);
            // null for normal options; 'left' or 'right' for matching.
            $table->string('side', 8)->nullable();
            $table->text('body');
            $table->string('media_path', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['question_id', 'key']);
        });

        // Append-only log that drives delta sync ("only what changed").
        Schema::create('content_changes', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 32);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('chapter_id')->nullable()->index();
            $table->string('action', 8);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_changes');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('passages');
        Schema::dropIfExists('flashcards');
        Schema::dropIfExists('solved_exercises');
        Schema::dropIfExists('summaries');
    }
};
