<?php

namespace Tests\Feature;

use App\Content\ContentInvalid;
use App\Content\TextbookReviewImporter;
use App\Models\ContentChange;
use App\Models\Subject;
use App\Models\TextbookReview;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TextbookReviewTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    private Unit $unit;

    private const GOOD = <<<'YAML'
source:
  book: Mathematics Student Textbook, Grade 12
  unit: 1
  unit_title: Sequences and Series
  exercise: Review Exercise
  pages: 57–60
  conventions: 'Variance divides by $n$.'
items:
  - ref: "1"
    question: 'List the first five terms where $a_n = 2n$.'
    steps:
      - '$a_1 = 2$, $a_2 = 4$'
    answer: '$2, 4, 6, 8, 10$'
  - ref: "2"
    question: 'Find the sums.'
    table: {x: [1, 2], f: [3, 4]}
    parts:
      - ref: a
        question: '$\sum_{k=1}^{3} k$'
        answer: '$6$'
      - ref: b
        question: '$\sum_{k=1}^{2} 3$'
        steps:
          - '$3 + 3$'
  - ref: "3"
    question: 'A question the book printed wrongly.'
    book_error: true
    note: 'Cannot be solved as printed.'
YAML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $maths = Subject::query()->where('code', 'mathematics')->firstOrFail();
        $this->unit = Unit::factory()->create(['subject_id' => $maths->id, 'grade' => 12, 'number' => 1]);
        $this->folder = storage_path('framework/testing/textbook-'.Str::random(8));
        $this->write('grade12/mathematics/review-u1.yaml', self::GOOD);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    private function write(string $relative, string $yaml): void
    {
        File::ensureDirectoryExists(dirname($this->folder.'/'.$relative));
        File::put($this->folder.'/'.$relative, $yaml);
    }

    private function importer(): TextbookReviewImporter
    {
        return TextbookReviewImporter::forKnownSubjects();
    }

    /**
     * @return list<string>
     */
    private function errorsFor(string $yaml, string $file = 'grade12/mathematics/review-u1.yaml'): array
    {
        File::deleteDirectory($this->folder);
        $this->write($file, $yaml);
        try {
            $this->importer()->load($this->folder);
        } catch (ContentInvalid $e) {
            return $e->errors;
        }
        $this->fail('The file was accepted.');
    }

    public function test_a_good_file_is_imported_and_reaches_the_app(): void
    {
        $report = $this->importer()->import($this->importer()->load($this->folder));
        $this->assertSame(1, $report->get('reviews_created'));

        $review = TextbookReview::query()->sole();
        $this->assertSame($this->unit->id, $review->unit_id);
        $this->assertSame(3, $review->question_count);
        $this->assertSame('57–60', $review->pages);
        $this->assertSame('Variance divides by $n$.', $review->intro);

        Sanctum::actingAs(User::factory()->create());
        /** @var list<array{id: int, review_question_count: int}> $units */
        $units = $this->getJson('/api/v1/catalog')->assertOk()->json('units');
        $this->assertSame([3], array_values(array_map(
            fn (array $u): int => $u['review_question_count'],
            array_filter($units, fn (array $u): bool => $u['id'] === $this->unit->id),
        )));

        $json = $this->getJson("/api/v1/units/{$this->unit->id}/review")->assertOk()->json();
        $this->assertSame('Mathematics Student Textbook, Grade 12', $json['book']);
        $this->assertSame(['1', '2', '3'], array_column($json['items'], 'ref'));
        $this->assertSame('$2, 4, 6, 8, 10$', $json['items'][0]['answer']);
        $this->assertSame(['x' => [1, 2], 'f' => [3, 4]], $json['items'][1]['table']);
        $this->assertSame(['a', 'b'], array_column($json['items'][1]['parts'], 'ref'));
        $this->assertTrue($json['items'][2]['book_error']);
        $this->assertFalse($json['items'][0]['book_error']);
    }

    public function test_a_new_or_changed_review_tells_the_app_to_refresh_its_catalog(): void
    {
        $since = ContentChange::latestCursor();
        $this->importer()->import($this->importer()->load($this->folder));

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/sync/changes?since={$since}")->assertOk()->assertJsonPath('catalog_changed', true);
    }

    public function test_importing_again_changes_nothing_and_edits_and_removals_are_applied(): void
    {
        $this->importer()->import($this->importer()->load($this->folder));
        $again = $this->importer()->import($this->importer()->load($this->folder));
        $this->assertSame(1, $again->get('reviews_unchanged'));
        $this->assertSame(0, $again->get('reviews_updated'));

        $this->write('grade12/mathematics/review-u1.yaml', str_replace('$2, 4, 6, 8, 10$', '$2,\ 4,\ 6,\ 8,\ 10$', self::GOOD));
        $edited = $this->importer()->import($this->importer()->load($this->folder));
        $this->assertSame(1, $edited->get('reviews_updated'));
        $this->assertSame('$2,\ 4,\ 6,\ 8,\ 10$', TextbookReview::query()->sole()->items[0]['answer']);

        // A second unit's file arrives, the first one's is removed.
        $maths = Subject::query()->where('code', 'mathematics')->firstOrFail();
        Unit::factory()->create(['subject_id' => $maths->id, 'grade' => 12, 'number' => 2]);
        File::delete($this->folder.'/grade12/mathematics/review-u1.yaml');
        $this->write('grade12/mathematics/review-u2.yaml', str_replace('unit: 1', 'unit: 2', self::GOOD));
        $moved = $this->importer()->import($this->importer()->load($this->folder));
        $this->assertSame(1, $moved->get('reviews_created'));
        $this->assertSame(1, $moved->get('reviews_removed'));
        $this->assertSame([2], TextbookReview::query()->with('unit')->get()->pluck('unit.number')->all());
    }

    public function test_bad_files_are_refused_with_clear_reasons(): void
    {
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: source.unit must be 1 (the number in the file name)',
            $this->errorsFor(str_replace('unit: 1', 'unit: 2', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 1: needs an answer (or parts with answers, or book_error: true)',
            $this->errorsFor(str_replace("    answer: '\$2, 4, 6, 8, 10\$'\n", '', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 2 part 2: needs an answer or steps',
            $this->errorsFor(str_replace("        steps:\n          - '\$3 + 3\$'\n", '', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 1: unknown key(s): answr',
            $this->errorsFor(str_replace('    answer: \'$2', '    answr: \'$2', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 3: ref "1" is used twice',
            $this->errorsFor(str_replace('  - ref: "3"', '  - ref: "1"', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 2: table must be a map of row name to a list of cells, all rows the same length',
            $this->errorsFor(str_replace('table: {x: [1, 2], f: [3, 4]}', 'table: [1, 2]', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/review-u1.yaml: item 2: table must be a map of row name to a list of cells, all rows the same length',
            $this->errorsFor(str_replace('f: [3, 4]', 'f: [3, 4, 5]', self::GOOD)),
        );
        $this->assertContains(
            'grade12/mathematics/unit1.yaml: unexpected file (use grade<N>/<subject>/review-u<unit>.yaml)',
            $this->errorsFor(self::GOOD, 'grade12/mathematics/unit1.yaml'),
        );
        $this->assertContains(
            'grade12/music/review-u1.yaml: unknown subject "music"',
            $this->errorsFor(self::GOOD, 'grade12/music/review-u1.yaml'),
        );
        $this->assertSame(0, TextbookReview::query()->count());
    }

    public function test_a_file_for_a_unit_that_is_not_in_the_database_stops_the_import(): void
    {
        $this->write('grade12/physics/review-u4.yaml', str_replace('unit: 1', 'unit: 4', self::GOOD));
        try {
            $this->importer()->import($this->importer()->load($this->folder));
            $this->fail('The import went ahead.');
        } catch (ContentInvalid $e) {
            $this->assertSame(['Grade 12 physics unit 4: no such unit in the database (run content:import first)'], $e->errors);
        }
        $this->assertSame(0, TextbookReview::query()->count());
    }

    public function test_the_command_imports_and_refuses_a_missing_folder(): void
    {
        $this->command('content:import-textbook', ['path' => $this->folder])
            ->expectsOutputToContain('Import finished.')
            ->assertSuccessful();
        $this->assertSame(1, TextbookReview::query()->count());

        $this->command('content:import-textbook', ['path' => $this->folder.'/nope'])
            ->expectsOutputToContain('Nothing was imported.')
            ->assertFailed();
    }

    public function test_the_review_needs_a_sign_in_and_a_unit_with_a_review(): void
    {
        $this->getJson("/api/v1/units/{$this->unit->id}/review")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/units/{$this->unit->id}/review")->assertNotFound();

        $this->importer()->import($this->importer()->load($this->folder));
        $this->unit->update(['is_active' => false]);
        $this->getJson("/api/v1/units/{$this->unit->id}/review")->assertNotFound();
    }
}
