<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed: subjects only. For local fake data run:
     *   php artisan db:seed --class=DemoDataSeeder
     */
    public function run(): void
    {
        $this->call(CurriculumSeeder::class);
    }
}
