<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'subject_'.$this->faker->unique()->numberBetween(1, 999999),
            'name' => ucfirst($this->faker->word()),
            'stream' => null,
            'is_exam_subject' => true,
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
