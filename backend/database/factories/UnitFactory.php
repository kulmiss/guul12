<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'grade' => 12,
            'number' => $this->faker->unique()->numberBetween(1, 999999),
            'title' => 'Unit '.$this->faker->numerify('###'),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
