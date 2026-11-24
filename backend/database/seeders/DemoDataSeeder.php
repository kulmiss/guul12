<?php

namespace Database\Seeders;

use App\Enums\Access;
use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\Summary;
use App\Models\Unit;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\StudentIdGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Fake data for local testing ONLY. Refuses to run in production.
 *
 * Creates: one demo chapter with a summary and questions of every type,
 * a super admin, a payments helper, a content checker and a few students (one Premium).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be loaded in production.');
        }

        $this->call(CurriculumSeeder::class);

        $physics = Subject::query()->where('code', 'physics')->firstOrFail();
        $unit = Unit::query()->updateOrCreate(
            ['subject_id' => $physics->id, 'grade' => 12, 'stream' => null, 'number' => 2],
            ['title' => 'Two-dimensional motion (demo)', 'sort' => 2, 'is_active' => true],
        );
        $chapter = Chapter::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'number' => 1],
            ['title' => 'Projectile motion (demo)', 'video_access' => Access::Free, 'sort' => 1, 'is_active' => true],
        );

        Summary::query()->updateOrCreate(['chapter_id' => $chapter->id], [
            'key_points' => [
                'A projectile moves sideways at constant speed while it falls with constant acceleration g.',
                'The time in the air depends only on the vertical motion.',
            ],
            'formulas' => [
                ['label' => 'Fall time from height h', 'latex' => 't = \\sqrt{\\frac{2h}{g}}'],
                ['label' => 'Horizontal distance', 'latex' => 'x = v_x t'],
            ],
            'status' => QuestionStatus::Published,
            'source' => 'Demo data (original)',
        ]);

        if (! $chapter->questions()->exists()) {
            $this->questions($chapter);
        }

        $this->people();
    }

    private function questions(Chapter $chapter): void
    {
        $make = function (QuestionType $type, string $stem, array $key, string $explanation, array $options = []) use ($chapter): void {
            $question = Question::query()->create([
                'chapter_id' => $chapter->id,
                'type' => $type,
                'stem' => $stem,
                'difficulty' => 2,
                'status' => QuestionStatus::Draft,
                'answer_key' => $key,
                'explanation' => $explanation,
                'source' => 'Demo data (original)',
            ]);
            foreach ($options as $i => [$optKey, $body, $side]) {
                QuestionOption::query()->create(['question_id' => $question->id, 'key' => $optKey, 'body' => $body, 'side' => $side, 'sort' => $i + 1]);
            }
            $question->update(['status' => QuestionStatus::Published]);
        };

        $make(QuestionType::SingleChoice,
            'A ball is thrown horizontally at 12 m/s from the top of a 20 m cliff. How long does it take to land? (g = 10 m/s²)',
            ['correct' => 'B'],
            'Only the vertical fall decides the time: $t = \\sqrt{2h/g} = \\sqrt{2 \\times 20 / 10} = 2$ s.',
            [['A', '1 s', null], ['B', '2 s', null], ['C', '1.7 s', null], ['D', '4 s', null]]);

        $make(QuestionType::TrueFalse,
            'The horizontal speed of a projectile changes during its flight (no air resistance).',
            ['correct' => 'FALSE'],
            'With no air resistance there is no horizontal force, so the horizontal speed stays the same.',
            [['TRUE', 'True', null], ['FALSE', 'False', null]]);

        $make(QuestionType::Numeric,
            'A stone falls freely from rest for 3 s. How far does it fall in metres? (g = 10 m/s²)',
            ['value' => 45, 'tolerance' => 0, 'unit' => 'm'],
            '$h = \\tfrac{1}{2} g t^2 = \\tfrac{1}{2} \\times 10 \\times 3^2 = 45$ m.');

        $make(QuestionType::FillIn,
            'The path of a projectile (no air resistance) has the shape of a ______.',
            ['accepted' => ['parabola']],
            'Constant horizontal speed plus constant vertical acceleration gives a parabola.');

        $make(QuestionType::MultipleAnswers,
            'Which quantities stay constant for a projectile with no air resistance? (choose all that apply)',
            ['correct' => ['A', 'C']],
            'The horizontal velocity and the acceleration (g, downward) stay constant; the vertical velocity and the speed change.',
            [['A', 'Horizontal velocity', null], ['B', 'Vertical velocity', null], ['C', 'Acceleration', null], ['D', 'Speed', null]]);

        $make(QuestionType::Matching,
            'Match each quantity with its SI unit.',
            ['pairs' => ['L1' => 'R2', 'L2' => 'R3', 'L3' => 'R1']],
            'Velocity is in m/s, acceleration in m/s², time in s.',
            [['L1', 'Velocity', 'left'], ['L2', 'Acceleration', 'left'], ['L3', 'Time', 'left'],
                ['R1', 's', 'right'], ['R2', 'm/s', 'right'], ['R3', 'm/s²', 'right']]);

        $make(QuestionType::Ordering,
            'Put the steps for finding the range of a horizontally launched projectile in order.',
            ['order' => ['B', 'A', 'C']],
            'First find the fall time from the height, then multiply it by the horizontal speed, then add the unit.',
            [['A', 'Multiply the time by the horizontal speed', null], ['B', 'Find the fall time from the height', null], ['C', 'Write the answer in metres', null]]);
    }

    private function people(): void
    {
        $staff = [
            ['admin@guul12.test', 'Demo Super Admin', UserRole::SuperAdmin],
            ['helper@guul12.test', 'Demo Payments Helper', UserRole::PaymentsHelper],
            ['checker@guul12.test', 'Demo Content Checker', UserRole::ContentChecker],
        ];

        foreach ($staff as [$email, $name, $role]) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }
            $user = new User;
            $user->student_id = app(StudentIdGenerator::class)->generate();
            $user->role = $role;
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make('demo-password-local');
            $user->save();
        }

        if (User::query()->where('role', UserRole::Student->value)->count() >= 3) {
            return;
        }

        $students = User::factory()->count(3)->create();
        $helper = User::query()->where('email', 'helper@guul12.test')->firstOrFail();
        app(PremiumService::class)->grant($students->firstOrFail(), PremiumPlan::Term, $helper, 'DEMO-TX-1');
    }
}
