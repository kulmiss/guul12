<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use App\Services\PaymentRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * The search box at the top of Nova: each role finds only what its job allows, the noisy
 * resources (options, settings, logs, reports …) stay out, staff never show up as students,
 * and every hit has a title and a short subtitle that say what it is.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class GlobalSearchNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private const SEARCHABLE = ['chapters', 'mocks', 'payment-requests', 'premium-grants', 'questions', 'staff', 'students', 'subjects'];

    /** @return list<array{resourceName: string, title: string, subTitle: ?string}> */
    private function search(User $as, string $text): array
    {
        /** @var list<array{resourceName: string, title: string, subTitle: ?string}> */
        return $this->actingAs($as)->getJson('/nova-api/search?search='.urlencode($text))->assertOk()->json();
    }

    /** @param  list<array{resourceName: string}>  $hits
     * @return list<string> */
    private function kinds(array $hits): array
    {
        $kinds = array_values(array_unique(array_column($hits, 'resourceName')));
        sort($kinds);

        return $kinds;
    }

    public function test_only_useful_resources_are_in_the_search_box(): void
    {
        $this->search(User::factory()->staff(UserRole::SuperAdmin)->create(), 'x'); // Nova registers its resources while serving
        $searchable = collect(Nova::$resources)
            ->filter(fn (string $resource): bool => $resource::$globallySearchable)
            ->map(fn (string $resource): string => $resource::uriKey())->sort()->values()->all();

        $this->assertSame(self::SEARCHABLE, $searchable);
    }

    public function test_each_role_finds_only_its_own_kind_of_thing(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create(['name' => 'Zebra Boss']);
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $student = User::factory()->create(['nickname' => 'Zebra1', 'grade' => 11, 'stream' => 'social']);
        $chapter = Chapter::factory()->create(['title' => 'Zebra crossings']);
        $question = Question::factory()->create(['chapter_id' => $chapter->id, 'stem' => 'How many stripes does a zebra have on its back legs?']);
        app(PaymentRequests::class)->create($student, PremiumPlan::Term, 'ZEBRA-7', 300, null, $helper);

        $this->assertSame(['chapters', 'questions'], $this->kinds($this->search($checker, 'zebra')));
        $this->assertSame(['payment-requests', 'students'], $this->kinds($this->search($helper, 'zebra')));
        $this->assertSame(['chapters', 'payment-requests', 'questions', 'staff', 'students'], $this->kinds($this->search($boss, 'zebra')));

        $hits = [];
        foreach ($this->search($boss, 'zebra') as $hit) {
            $hits[$hit['resourceName']] ??= $hit;
        }
        $this->assertSame($student->student_id, $hits['students']['title'], 'only the student, not "Zebra Boss"');
        $this->assertSame('Grade 11 · Social · Zebra1', $hits['students']['subTitle']);
        $this->assertSame("#{$question->id} How many stripes does a zebra have on its back legs?", $hits['questions']['title']);
        $this->assertSame('Zebra crossings · Published', $hits['questions']['subTitle']);
        $this->assertStringStartsWith('Grade 12 · ', (string) $hits['chapters']['subTitle']);
        $this->assertSame('ZEBRA-7', $hits['payment-requests']['title']);
        $this->assertSame("{$student->student_id} · Term · Waiting", $hits['payment-requests']['subTitle']);
        $this->assertSame('Zebra Boss', $hits['staff']['title']);
        $this->assertSame('Super admin', $hits['staff']['subTitle']);
        $this->assertCount(1, collect($this->search($boss, 'zebra'))->where('resourceName', 'students'));
    }

    public function test_long_question_titles_are_cut(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $question = Question::factory()->create(['stem' => str_repeat('Long words here ', 20)]);

        $title = (string) (collect($this->search($boss, (string) $question->id))->firstWhere('resourceName', 'questions')['title'] ?? '');
        $this->assertStringStartsWith("#{$question->id} Long words here", $title);
        $this->assertLessThanOrEqual(80, mb_strlen($title));
    }
}
