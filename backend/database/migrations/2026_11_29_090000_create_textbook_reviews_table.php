<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The review exercise at the end of each textbook unit, solved (owner has permission to
        // use the textbook exercises, DECISIONS.md #44). One row per unit; the questions with
        // their parts, steps and answers are stored as JSON, exactly as imported.
        Schema::create('textbook_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('book', 160);
            $table->string('pages', 20)->nullable();
            $table->text('intro')->nullable();
            // Text, not a JSON column: MySQL 8 reorders the keys of JSON objects, and the rows of a
            // data table must stay in the book's order.
            $table->longText('items');
            $table->unsignedSmallInteger('question_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textbook_reviews');
    }
};
