<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\Scoring\AnswerScorer;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_seeder_creates_the_exam_subjects_and_can_run_twice(): void
    {
        $this->seed(CurriculumSeeder::class);
        $this->seed(CurriculumSeeder::class);

        $this->assertSame(count(CurriculumSeeder::SUBJECTS), Subject::query()->count());
        $this->assertSame('natural', Subject::query()->where('code', 'physics')->value('stream'));
        $this->assertNull(Subject::query()->where('code', 'aptitude')->value('stream'));
    }

    public function test_demo_data_refuses_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->app->make(DemoDataSeeder::class)->run();
            $this->fail('Demo seeder ran in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('never be loaded in production', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_demo_data_has_every_question_type_and_its_keys_are_right(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $questions = Question::query()->get();
        $this->assertCount(7, $questions);
        $this->assertTrue($questions->every(fn (Question $q): bool => $q->status === QuestionStatus::Published));

        $scorer = new AnswerScorer;
        foreach ($questions as $question) {
            // Answers solved independently of the stored keys.
            $answer = match ($question->type) {
                QuestionType::SingleChoice => 'B',
                QuestionType::TrueFalse => 'FALSE',
                QuestionType::Numeric => '45',
                QuestionType::FillIn => 'Parabola',
                QuestionType::MultipleAnswers => ['C', 'A'],
                QuestionType::Matching => ['L1' => 'R2', 'L2' => 'R3', 'L3' => 'R1'],
                QuestionType::Ordering => ['B', 'A', 'C'],
                default => null,
            };
            $this->assertNotNull($answer, "Unexpected type {$question->type->value}");
            $this->assertTrue(
                $scorer->isCorrect($question->type, $question->answer_key, $answer),
                "Demo key wrong for {$question->type->value}"
            );
        }

        $this->assertSame(3, User::query()->where('role', 'student')->count());
        $premium = User::query()->where('role', 'student')->get()->filter(fn (User $u): bool => app(PremiumService::class)->isPremium($u));
        $this->assertCount(1, $premium);
    }
}
