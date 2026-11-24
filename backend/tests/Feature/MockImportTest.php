<?php

namespace Tests\Feature;

use App\Content\ContentFolder;
use App\Content\ContentImporter;
use App\Content\ContentInvalid;
use App\Content\ImportReport;
use App\Enums\Access;
use App\Models\Mock;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentFixture;
use Tests\TestCase;

class MockImportTest extends TestCase
{
    use RefreshDatabase;

    private ContentFixture $fx;

    private const FILE = 'physics/u2-c1.yaml';

    /** Answer letters with no run and no A, B, C, D pattern; q01 (key B) goes first. */
    private const LETTERS = ['A', 'C', 'B', 'D', 'B', 'A', 'D', 'C', 'A', 'B', 'D', 'C'];

    /** @var list<string> */
    private array $extra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $this->fx = new ContentFixture;
        $this->extra = $this->fx->addChoiceQuestions(self::LETTERS);
    }

    protected function tearDown(): void
    {
        $this->fx->delete();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mockFile(array $overrides = []): array
    {
        return array_merge([
            'uid' => 'g12-mock-physics-1',
            'subject' => 'physics',
            'number' => 1,
            'title' => 'Physics mock exam 1',
            'minutes' => 30,
            'access' => 'free',
            'questions' => ['g12-physics-2-1-q01', ...$this->extra],
        ], $overrides);
    }

    private function import(): ImportReport
    {
        $bundle = ContentFolder::validator()->load($this->fx->write());

        return $this->app->make(ContentImporter::class)->import($bundle['curricula'], $bundle['reviews']);
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        try {
            ContentFolder::validator()->load($this->fx->write());
        } catch (ContentInvalid $e) {
            return $e->errors;
        }

        return [];
    }

    public function test_a_mock_is_imported_in_file_order_and_kept_in_step(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->fx->mocks['physics-1.yaml'] = $this->mockFile();
        $report = $this->import();

        $this->assertSame(1, $report->get('mocks_created'));
        $this->assertSame([], $report->problems);
        $mock = Mock::query()->where('uid', 'g12-mock-physics-1')->firstOrFail();
        $this->assertSame(['physics', 12, 1, 30, Access::Free, true], [$mock->subject->code, $mock->grade, $mock->number, $mock->minutes, $mock->access, $mock->is_active]);
        $this->assertSame(['g12-physics-2-1-q01', ...$this->extra], $mock->questions()->pluck('uid')->all());

        $report = $this->import();
        $this->assertSame(1, $report->get('mocks_unchanged'));

        // New order and Premium: updated in place.
        $reordered = $this->mockFile(['access' => 'premium', 'questions' => [...$this->extra, 'g12-physics-2-1-q01']]);
        $this->fx->mocks['physics-1.yaml'] = $reordered;
        $report = $this->import();
        $this->assertSame(1, $report->get('mocks_updated'));
        $this->assertSame(Access::Premium, $mock->refresh()->access);
        $this->assertSame([...$this->extra, 'g12-physics-2-1-q01'], $mock->questions()->pluck('uid')->all());
        $this->assertSame(1, Mock::query()->count());

        // File removed: the mock is hidden, not deleted (old exam results keep their mock).
        $this->fx->mocks = [];
        $report = $this->import();
        $this->assertSame(1, $report->get('mocks_hidden'));
        $this->assertFalse($mock->refresh()->is_active);
    }

    public function test_a_hidden_question_makes_the_mock_short_and_is_reported(): void
    {
        $this->fx->writeReview(self::FILE, skip: ['q105']);
        $this->fx->mocks['physics-1.yaml'] = $this->mockFile();
        $report = $this->import();

        $this->assertContains('g12-mock-physics-1: g12-physics-2-1-q105 is hidden (draft), so the mock is short', $report->problems);
    }

    public function test_the_validator_refuses_broken_mocks(): void
    {
        $base = ['g12-physics-2-1-q01', ...$this->extra];
        $cases = [
            'unknown question' => [['questions' => [...$base, 'g12-physics-2-1-q999']], "unknown question 'g12-physics-2-1-q999'"],
            'not a choice question' => [['questions' => [...$base, 'g12-physics-2-1-q02']], 'is not a choice question'],
            'listed twice' => [['questions' => [...$base, 'g12-physics-2-1-q01']], 'g12-physics-2-1-q01 is listed twice'],
            'too few' => [['questions' => array_slice($base, 0, 9)], 'a mock needs 10–200 questions (has 9)'],
            'wrong uid' => [['uid' => 'g12-mock-physics-2'], "uid must be 'g12-mock-physics-1'"],
            'bad access' => [['access' => 'paid'], "access must be 'free' or 'premium'"],
            'unknown subject' => [['subject' => 'music'], "subject 'music' is not in curriculum.yaml"],
            'bad minutes' => [['minutes' => 0], 'minutes must be a whole number 5–300'],
        ];
        foreach ($cases as $name => [$overrides, $message]) {
            $this->fx->mocks = ['physics-1.yaml' => $this->mockFile($overrides)];
            $errors = $this->errors();
            $this->assertCount(1, $errors, $name.': '.implode(' | ', $errors));
            $this->assertStringContainsString($message, $errors[0], $name);
        }
    }

    public function test_a_question_may_sit_in_only_one_mock(): void
    {
        $this->fx->mocks['physics-1.yaml'] = $this->mockFile();
        $this->fx->mocks['physics-2.yaml'] = $this->mockFile(['uid' => 'g12-mock-physics-2', 'number' => 2]);

        $errors = $this->errors();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('g12-physics-2-1-q01 is already in grade12/mocks/physics-1.yaml', $errors[0]);
    }

    public function test_the_key_must_not_show_a_pattern(): void
    {
        // Same letter four times in a row (q113–q122).
        $same = $this->fx->addChoiceQuestions(['A', 'C', 'C', 'C', 'C', 'A', 'B', 'D', 'B', 'D']);
        $this->fx->mocks = ['physics-1.yaml' => $this->mockFile(['questions' => $same])];
        $this->assertSame(["grade12/mocks/physics-1.yaml: the key is 'C' four times in a row (question 5)"], $this->errors());

        // A, B, C, D, A in a row.
        $this->fx->mocks = ['physics-1.yaml' => $this->mockFile(['questions' => [
            'g12-physics-2-1-q101', // A
            'g12-physics-2-1-q103', // B
            'g12-physics-2-1-q102', // C
            'g12-physics-2-1-q104', // D
            'g12-physics-2-1-q106', // A  ← fifth in the run
            'g12-physics-2-1-q105', // B
            'g12-physics-2-1-q107', // D
            'g12-physics-2-1-q108', // C
            'g12-physics-2-1-q111', // D
            'g12-physics-2-1-q112', // C
        ]])];
        $this->assertSame(['grade12/mocks/physics-1.yaml: the key runs A, B, C, D in order (question 5)'], $this->errors());
    }
}
