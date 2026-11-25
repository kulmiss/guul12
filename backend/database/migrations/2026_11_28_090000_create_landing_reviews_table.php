<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real student reviews for the website, added by staff in Nova. Only reviews with the student's
        // permission and switched on are shown; the section is hidden while there are none.
        // First name, grade and city only: never a photo, phone number or account link.
        Schema::create('landing_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->unsignedTinyInteger('grade');
            $table->string('city', 60)->nullable();
            $table->string('quote', 300);
            $table->unsignedTinyInteger('rating');
            $table->boolean('permission_confirmed')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_reviews');
    }
};
