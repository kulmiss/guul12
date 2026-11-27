<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Questions the student really answered (not skipped). The 50 exam points need 10.
            $table->unsignedSmallInteger('answered')->default(0)->after('correct');
            // A fixed mock sent far after its time limit: saved, but no exam points and no rival.
            $table->boolean('over_time')->default(false)->after('answered');
        });

        DB::table('attempts')->update(['answered' => DB::raw(
            '(SELECT COUNT(*) FROM attempt_answers WHERE attempt_answers.attempt_id = attempts.id AND attempt_answers.response IS NOT NULL)'
        )]);
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['answered', 'over_time']);
        });
    }
};
