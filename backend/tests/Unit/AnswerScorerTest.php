<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Services\Scoring\AnswerScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnswerScorerTest extends TestCase
{
    private AnswerScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new AnswerScorer;
    }

    /**
     * @return array<string, array{QuestionType, array<string, mixed>, mixed, bool}>
     */
    public static function cases(): array
    {
        return [
            // Single key types
            'single correct' => [QuestionType::SingleChoice, ['correct' => 'B'], 'B', true],
            'single lowercase' => [QuestionType::SingleChoice, ['correct' => 'B'], ' b ', true],
            'single wrong' => [QuestionType::SingleChoice, ['correct' => 'B'], 'C', false],
            'single skipped' => [QuestionType::SingleChoice, ['correct' => 'B'], null, false],
            'single array given' => [QuestionType::SingleChoice, ['correct' => 'B'], ['B'], false],
            'single number given' => [QuestionType::SingleChoice, ['correct' => 'B'], 2, false],
            'single broken key' => [QuestionType::SingleChoice, ['correct' => ['B']], 'B', false],
            'true false' => [QuestionType::TrueFalse, ['correct' => 'TRUE'], 'true', true],
            'true false wrong' => [QuestionType::TrueFalse, ['correct' => 'TRUE'], 'FALSE', false],
            'image choice' => [QuestionType::ImageChoice, ['correct' => 'D'], 'D', true],
            'passage choice' => [QuestionType::PassageChoice, ['correct' => 'A'], 'A', true],

            // Multiple answers
            'multi exact' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['A', 'C'], true],
            'multi other order' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['c', 'a'], true],
            'multi repeated key' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['A', 'A', 'C'], true],
            'multi missing one' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['A'], false],
            'multi extra one' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['A', 'B', 'C'], false],
            'multi empty' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], [], false],
            'multi string given' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], 'A,C', false],
            'multi object given' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['x' => 'A', 'y' => 'C'], false],
            'multi number in list' => [QuestionType::MultipleAnswers, ['correct' => ['A', 'C']], ['A', 3], false],
            'multi empty key never correct' => [QuestionType::MultipleAnswers, ['correct' => []], [], false],

            // Numeric
            'numeric exact int' => [QuestionType::Numeric, ['value' => 2], 2, true],
            'numeric exact float' => [QuestionType::Numeric, ['value' => 2.5], 2.5, true],
            'numeric string' => [QuestionType::Numeric, ['value' => 2.5], '2.5', true],
            'numeric decimal comma' => [QuestionType::Numeric, ['value' => 2.5], '2,5', true],
            'numeric fraction' => [QuestionType::Numeric, ['value' => 2.5], '5/2', true],
            'numeric negative fraction' => [QuestionType::Numeric, ['value' => -0.5], '-1/2', true],
            'numeric unicode minus' => [QuestionType::Numeric, ['value' => -3], "\u{2212}3", true],
            'numeric spaces' => [QuestionType::Numeric, ['value' => 1000], ' 1 000 ', true],
            'numeric within tolerance' => [QuestionType::Numeric, ['value' => 9.8, 'tolerance' => 0.05], '9.84', true],
            'numeric outside tolerance' => [QuestionType::Numeric, ['value' => 9.8, 'tolerance' => 0.05], '9.86', false],
            'numeric float noise' => [QuestionType::Numeric, ['value' => 0.3], 0.1 + 0.2, true],
            'numeric wrong' => [QuestionType::Numeric, ['value' => 2], '3', false],
            'numeric divide by zero' => [QuestionType::Numeric, ['value' => 2], '4/0', false],
            'numeric text' => [QuestionType::Numeric, ['value' => 2], 'two', false],
            'numeric with unit text' => [QuestionType::Numeric, ['value' => 2], '2 s', false],
            'numeric thousands comma is not decimal when dot present' => [QuestionType::Numeric, ['value' => 1000.5], '1,000.5', false],
            'numeric empty string' => [QuestionType::Numeric, ['value' => 0], '', false],
            'numeric scientific' => [QuestionType::Numeric, ['value' => 0.00012], '1.2e-4', true],
            'numeric broken key' => [QuestionType::Numeric, ['value' => '2'], '2', false],
            'numeric bool given' => [QuestionType::Numeric, ['value' => 1], true, false],

            // Fill in
            'fill exact' => [QuestionType::FillIn, ['accepted' => ['photosynthesis']], 'photosynthesis', true],
            'fill case and spaces' => [QuestionType::FillIn, ['accepted' => ['Photosynthesis']], '  PHOTOSYNTHESIS. ', true],
            'fill inner spaces' => [QuestionType::FillIn, ['accepted' => ['carbon dioxide']], 'carbon   dioxide', true],
            'fill second spelling' => [QuestionType::FillIn, ['accepted' => ['ion', 'ions']], 'ions', true],
            'fill wrong' => [QuestionType::FillIn, ['accepted' => ['ion']], 'atom', false],
            'fill empty' => [QuestionType::FillIn, ['accepted' => ['ion']], '   ', false],
            'fill not a string' => [QuestionType::FillIn, ['accepted' => ['2']], 2, false],

            // Matching
            'match all right' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2', 'L2' => 'R1']], ['L1' => 'R2', 'L2' => 'R1'], true],
            'match lowercase values' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2', 'L2' => 'R1']], ['L1' => 'r2', 'L2' => 'r1'], true],
            'match one wrong' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2', 'L2' => 'R1']], ['L1' => 'R1', 'L2' => 'R1'], false],
            'match missing' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2', 'L2' => 'R1']], ['L1' => 'R2'], false],
            'match extra' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2']], ['L1' => 'R2', 'L9' => 'R1'], false],
            'match list given' => [QuestionType::Matching, ['pairs' => ['L1' => 'R2']], ['R2'], false],
            'match empty key' => [QuestionType::Matching, ['pairs' => []], [], false],

            // Ordering
            'order right' => [QuestionType::Ordering, ['order' => ['C', 'A', 'B']], ['C', 'A', 'B'], true],
            'order wrong' => [QuestionType::Ordering, ['order' => ['C', 'A', 'B']], ['A', 'C', 'B'], false],
            'order short' => [QuestionType::Ordering, ['order' => ['C', 'A', 'B']], ['C', 'A'], false],
            'order object given' => [QuestionType::Ordering, ['order' => ['C', 'A']], ['x' => 'C', 'y' => 'A'], false],
        ];
    }

    /**
     * @param  array<string, mixed>  $key
     */
    #[DataProvider('cases')]
    public function test_scores_responses(QuestionType $type, array $key, mixed $response, bool $expected): void
    {
        $this->assertSame($expected, $this->scorer->isCorrect($type, $key, $response));
    }

    public function test_every_type_is_handled(): void
    {
        foreach (QuestionType::cases() as $type) {
            // Must not throw UnhandledMatchError for any type.
            $this->assertFalse($this->scorer->isCorrect($type, [], 'x'));
        }
    }

    public function test_parse_number_rejects_non_finite(): void
    {
        $this->assertNull(AnswerScorer::parseNumber(INF));
        $this->assertNull(AnswerScorer::parseNumber('1e999'));
        $this->assertNull(AnswerScorer::parseNumber(NAN));
    }
}
