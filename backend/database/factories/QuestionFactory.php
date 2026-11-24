<?php

namespace Database\Factories;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Default: a published single-choice question "2 + 2 = ?" with answer B.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'type' => QuestionType::SingleChoice,
            'stem' => 'What is 2 + 2?',
            'difficulty' => 2,
            'status' => QuestionStatus::Published,
            'answer_key' => ['correct' => 'B'],
            'explanation' => '2 + 2 = 4.',
        ];
    }

    public function status(QuestionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /**
     * Adds options A–D (4 is B) after creating.
     */
    public function withOptions(): static
    {
        return $this->afterCreating(function (Question $question): void {
            foreach (['A' => '3', 'B' => '4', 'C' => '5', 'D' => '22'] as $i => $body) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'key' => $i,
                    'body' => $body,
                    'sort' => ord($i) - 64,
                ]);
            }
            $question->refresh();
        });
    }
}
