<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name', 96);
            // null = taken by both streams (English, Aptitude, Grades 9–10 subjects).
            $table->string('stream', 16)->nullable()->index();
            $table->boolean('is_exam_subject')->default(true);
            $table->string('icon', 48)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            // null = both streams. Used where a subject splits by stream (Mathematics in Grades 11–12).
            $table->string('stream', 16)->nullable();
            $table->unsignedSmallInteger('number');
            $table->string('title', 191);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subject_id', 'grade', 'stream', 'number']);
            $table->index(['subject_id', 'grade']);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('title', 191);
            $table->string('textbook_url', 512)->nullable();
            $table->string('video_youtube_id', 32)->nullable();
            $table->string('video_access', 16)->default('free');
            $table->string('summary_pdf_path', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('units');
        Schema::dropIfExists('subjects');
    }
};
