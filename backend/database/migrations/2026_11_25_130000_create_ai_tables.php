<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI explanations, saved and reused for every student (one AI call per question version).
        Schema::create('ai_explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_version');
            $table->text('body');
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            // Millionths of a US dollar.
            $table->unsignedInteger('cost_micro_usd');
            $table->unsignedInteger('helpful')->default(0);
            $table->unsignedInteger('not_helpful')->default(0);
            // Hidden after too many "not helpful": the next request makes a new one.
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->index(['question_id', 'question_version', 'hidden']);
            $table->index('created_at');
        });

        // Every "Explain more" tap (for the daily limit), cached or not.
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_explanation_id')->constrained()->cascadeOnDelete();
            $table->boolean('cached');
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_explanation_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['user_id', 'ai_explanation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_explanations');
    }
};
