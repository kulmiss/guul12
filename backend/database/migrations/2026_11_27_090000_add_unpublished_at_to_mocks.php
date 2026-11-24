<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set by staff in Nova ("Unpublish"). The content import switches is_active on and off; it never
        // touches this, so a staff decision is not undone by the next import.
        Schema::table('mocks', function (Blueprint $table) {
            $table->timestamp('unpublished_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('mocks', function (Blueprint $table) {
            $table->dropColumn('unpublished_at');
        });
    }
};
