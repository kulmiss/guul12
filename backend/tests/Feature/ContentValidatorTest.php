<?php

namespace Tests\Feature;

use App\Content\ContentFolder;
use App\Content\ContentInvalid;
use App\Content\QuestionDef;
use App\Enums\QuestionType;
use App\Services\Scoring\AnswerScorer;
use Tests\Support\ContentFixture;
use Tests\TestCase;

class ContentValidatorTest extends TestCase
{
    private ContentFixture $fx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new ContentFixture;
    }

    protected function tearDown(): void
    {
        $this->fx->delete();
        parent::tearDown();
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

    private function assertError(string $needle): void
    {
        $errors = $this->errors();
        $this->assertNotEmpty(array_filter($errors, fn (string $e) => str_contains($e, $needle)), "Expected an error containing '{$needle}', got:\n".implode("\n", $errors));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  list<string>  $remove
     */
    private function editQuestion(int $index, array $changes, array $remove = []): void
    {
        $q = &$this->fx->chapters['physics/u2-c1.yaml']['questions'][$index];
        $q = array_merge($q, $changes);
        foreach ($remove as $key) {
            unset($q[$key]);
        }
    }

    public function test_valid_folder_builds_every_question_type_with_scorer_keys(): void
    {
        $bundle = ContentFolder::validator()->load($this->fx->write());
        $chapters = $bundle['curricula'][0]->subjects[0]->units[0]->chapters;

        $this->assertCount(2, $chapters);
        $this->assertSame('g12-physics-2-1', $chapters[0]->code);
        $this->assertNull($chapters[1]->file, 'planned chapter without content');
        $this->assertCount(8, $chapters[0]->questions);

        $byType = [];
        foreach ($chapters[0]->questions as $q) {
            $byType[$q->type->value] = $q;
        }
        $scorer = new AnswerScorer;
        $right = [
            'single_choice' => 'B', 'true_false' => 'FALSE', 'multiple_answers' => ['C', 'A'], 'numeric' => '3',
            'fill_in' => 'Parabola', 'matching' => ['L1' => 'R2', 'L2' => 'R1'], 'ordering' => ['B', 'A', 'C'], 'passage_choice' => 'A',
        ];
        foreach ($right as $type => $response) {
            $q = $byType[$type] ?? null;
            $this->assertInstanceOf(QuestionDef::class, $q);
            $this->assertTrue($scorer->isCorrect($q->type, $q->answerKey, $response), "{$type} key");
        }
        $type = fn (string $t): QuestionDef => $byType[$t] ?? throw new \LogicException("no {$t}");
        $this->assertSame(['TRUE', 'FALSE'], array_map(fn ($o) => $o->key, $type('true_false')->options));
        $this->assertSame(['value' => 3, 'tolerance' => 0, 'unit' => 's'], $type('numeric')->answerKey);
        $this->assertSame('left', $type('matching')->options[0]->side);
        $this->assertSame(QuestionType::PassageChoice, $type('passage_choice')->type);
    }

    public function test_review_hash_ignores_key_and_explanation_but_not_text(): void
    {
        $bundle = ContentFolder::validator()->load($this->fx->write());
        $hash = $bundle['curricula'][0]->subjects[0]->units[0]->chapters[0]->questions[0]->reviewHash(null);

        $this->editQuestion(0, ['answer' => 'A', 'explanation' => 'A completely different explanation text.']);
        $bundle = ContentFolder::validator()->load($this->fx->write());
        $this->assertSame($hash, $bundle['curricula'][0]->subjects[0]->units[0]->chapters[0]->questions[0]->reviewHash(null));

        $this->editQuestion(0, ['stem' => 'Which quantity stays constant for a projectile without air resistance?']);
        $bundle = ContentFolder::validator()->load($this->fx->write());
        $this->assertNotSame($hash, $bundle['curricula'][0]->subjects[0]->units[0]->chapters[0]->questions[0]->reviewHash(null));
    }

    public function test_unknown_subject_and_repeated_units(): void
    {
        $this->fx->curriculum['subjects'][0]['code'] = 'astrology';
        $this->assertError("unknown subject 'astrology'");
    }

    public function test_uid_rules(): void
    {
        $this->editQuestion(1, ['uid' => 'g12-physics-2-1-q01']);
        $this->assertError('is already used');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(1, ['uid' => 'g12-chemistry-1-1-q02']);
        $this->assertError("must start with 'g12-physics-2-1-'");
    }

    public function test_choice_rules(): void
    {
        $this->editQuestion(0, ['options' => ['A' => 'x', 'C' => 'y', 'D' => 'z']]);
        $this->assertError('option letters must be A, B, C');

        $this->editQuestion(0, ['options' => ['A' => 'Same', 'B' => 'same ', 'C' => 'Other']]);
        $this->assertError('two items say the same thing');

        $this->editQuestion(0, ['options' => ['A' => 'x', 'B' => 'y', 'C' => 'z'], 'answer' => 'D']);
        $this->assertError('answer must be one of the option letters');

        $this->editQuestion(0, ['options' => ['A' => 'x', 'B' => 'y']]);
        $this->assertError('needs 3–6 options');
    }

    public function test_type_specific_rules(): void
    {
        $this->editQuestion(2, ['answer' => ['A']]);
        $this->assertError('needs at least 2 correct letters');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(1, ['answer' => 'false']);
        $this->assertError('answer must be true or false');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(3, ['answer' => ['value' => 3, 'tolerance' => -1]]);
        $this->assertError('tolerance must be a number ≥ 0');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(5, ['answer' => ['L1' => 'R1', 'L2' => 'R1']]);
        $this->assertError('every left item needs its own, different right item');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(6, ['answer' => ['B', 'A']]);
        $this->assertError('answer must use every item key exactly once');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(7, [], ['passage']);
        $this->assertError('passage_choice needs a passage');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(0, ['type' => 'image_choice']);
        $this->assertError('image_choice needs media');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(4, ['answer' => ['parabola', ' Parabola']]);
        $this->assertError('repeats another');
    }

    public function test_text_rules(): void
    {
        $this->editQuestion(0, ['stem' => 'What is $x^2 when x = 3?']);
        $this->assertError('unbalanced $');

        $this->editQuestion(0, ['stem' => 'What is $\frac{1}{2$ of 8?']);
        $this->assertError('unbalanced { } in maths');

        $this->editQuestion(0, ['stem' => 'It costs \$5. Which quantity stays constant?']);
        $this->assertSame([], $this->errors(), 'an escaped dollar sign is fine');

        $this->editQuestion(0, ['explanation' => 'Because.']);
        $this->assertError('explanation is too short');

        $this->editQuestion(0, ['explanation' => 'Long enough explanation <script>alert(1)</script>']);
        $this->assertError('HTML is not allowed');

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(0, ['source' => 'copied from a website']);
        $this->assertError("source must be 'original'");

        $this->fx->chapters['physics/u2-c1.yaml'] = ContentFixture::fullChapter();
        $this->editQuestion(0, ['hint' => 'x']);
        $this->assertError("unknown field 'hint'");
    }

    public function test_the_same_question_twice_anywhere_is_refused(): void
    {
        $this->fx->curriculum['subjects'][0]['units'][0]['chapters'][1]['file'] = 'physics/u2-c2.yaml';
        $copy = ContentFixture::fullChapter()['questions'][0];
        $copy['uid'] = 'g12-physics-2-2-q01';
        $copy['stem'] = '  which QUANTITY stays constant for a projectile (no air)? ';
        $this->fx->chapters['physics/u2-c2.yaml'] = ['questions' => [$copy]];
        $this->assertError('duplicate of g12-physics-2-1-q01');
    }

    public function test_right_answers_must_not_sit_on_one_letter(): void
    {
        $questions = [];
        for ($i = 1; $i <= 10; $i++) {
            $questions[] = [
                'uid' => sprintf('g12-physics-2-1-z%02d', $i), 'type' => 'single_choice', 'difficulty' => 2,
                'stem' => "What is {$i} + 1?",
                'options' => ['A' => (string) ($i + 1), 'B' => (string) ($i + 2), 'C' => (string) ($i + 3)],
                'answer' => $i <= 5 ? 'A' : ['B', 'C'][$i % 2], 'explanation' => "Adding one to {$i} gives ".($i + 1).'.',
            ];
        }
        $this->fx->chapters['physics/u2-c1.yaml'] = ['questions' => $questions];
        $this->assertError("the right answer is 'A' in 5 of 10");
    }

    public function test_missing_chapter_file_and_bad_yaml(): void
    {
        $this->fx->curriculum['subjects'][0]['units'][0]['chapters'][1]['file'] = 'physics/missing.yaml';
        $this->assertError('file not found: physics/missing.yaml');

        $this->fx->write();
        file_put_contents($this->fx->root.'/grade12/physics/u2-c1.yaml', "questions: [\n  - uid: x\n bad");
        try {
            ContentFolder::validator()->load($this->fx->root);
            $this->fail('expected invalid');
        } catch (ContentInvalid $e) {
            $this->assertNotEmpty($e->errors);
        }
    }

    public function test_content_check_command(): void
    {
        $this->command('content:check', ['path' => $this->fx->write()])
            ->expectsOutputToContain('Content is valid.')
            ->assertSuccessful();

        $this->editQuestion(0, ['answer' => 'Z']);
        $this->command('content:check', ['path' => $this->fx->write()])
            ->expectsOutputToContain('Nothing may be imported')
            ->assertFailed();
    }

    public function test_review_sheet_has_no_answers_or_explanations(): void
    {
        $this->fx->write();
        $out = $this->fx->root.'/sheet.yaml';
        $this->command('content:review-sheet', ['file' => 'grade12/physics/u2-c1.yaml', '--path' => $this->fx->root, '--out' => $out])
            ->assertSuccessful();
        $sheet = (string) file_get_contents($out);

        $this->assertStringContainsString('g12-physics-2-1-q01', $sheet);
        $this->assertStringContainsString('hash:', $sheet);
        $this->assertStringContainsString('Launch day', $sheet, 'passage text is included');
        $this->assertStringNotContainsString('explanation', $sheet);
        $this->assertStringNotContainsString('No horizontal force acts', $sheet);
        $this->assertStringNotContainsString('answer: B', $sheet);
        $this->assertStringNotContainsString('parabola', strtolower(str_replace('Parabola', '', $sheet)), 'fill-in answers are not leaked');
    }
}
