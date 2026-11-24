<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fixed mock exams: a set list of bank questions in a set order (content/<grade>/mocks/*.yaml).
        Schema::create('mocks', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 64)->unique();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->unsignedSmallInteger('number');
            $table->string('title', 191);
            $table->unsignedSmallInteger('minutes');
            $table->string('access', 16)->default('free');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['subject_id', 'grade', 'number']);
        });

        Schema::create('mock_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort');
            $table->unique(['mock_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_questions');
        Schema::dropIfExists('mocks');
    }
};
