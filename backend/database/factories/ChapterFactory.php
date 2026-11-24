<?php

namespace Database\Factories;

use App\Enums\Access;
use App\Models\Chapter;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 65000), // unsigned smallint (MySQL)
            'title' => 'Chapter '.$this->faker->numerify('###'),
            'video_access' => Access::Free,
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
