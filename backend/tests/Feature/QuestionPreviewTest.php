<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\QuestionPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Nova question preview shows checkers what students see, and never runs what staff typed.
 */
class QuestionPreviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string|array{0: string, 1: string}>  $options  key => body, or key => [side, body]
     */
    private function question(array $attributes, array $options = []): Question
    {
        $question = Question::factory()->create($attributes);
        $sort = 0;
        foreach ($options as $key => $body) {
            QuestionOption::query()->create([
                'question_id' => $question->id,
                'key' => $key,
                'side' => is_array($body) ? $body[0] : null,
                'body' => is_array($body) ? $body[1] : $body,
                'sort' => ++$sort,
            ]);
        }

        return $question->refresh()->load('options', 'passage');
    }

    public function test_single_choice_marks_the_right_option_and_escapes_text(): void
    {
        $html = QuestionPreview::html($this->question(
            ['stem' => 'Solve $x^2 = 4$ <script>alert(1)</script>', 'answer_key' => ['correct' => 'b'], 'explanation' => 'So $x = 2$ & more.'],
            ['A' => '$x = 1$', 'B' => '$x = 2$', 'C' => '<b>3</b>'],
        ));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<b>3</b>', $html);
        $this->assertStringContainsString('<span class="guul-tex" data-display="0">x^2 = 4</span>', $html);
        $this->assertSame(1, substr_count($html, 'title="Right answer"'), 'exactly one option is marked right');
        $this->assertMatchesRegularExpression('/<li class="gp-option gp-right"><span class="gp-key">B<\/span><span class="gp-body"><span class="guul-tex" data-display="0">x = 2<\/span><\/span><span class="gp-tick" title="Right answer">/', $html);
        $this->assertStringContainsString('Explanation: </span>So <span class="guul-tex" data-display="0">x = 2</span> &amp; more.', $html);
    }

    public function test_multiple_answers_mark_every_right_option(): void
    {
        $html = QuestionPreview::html($this->question(
            ['type' => QuestionType::MultipleAnswers, 'answer_key' => ['correct' => ['A', 'C']]],
            ['A' => 'one', 'B' => 'two', 'C' => 'three'],
        ));

        $this->assertSame(2, substr_count($html, 'title="Right answer"'));
    }

    public function test_pictures_use_the_public_storage_url_and_paths_are_escaped(): void
    {
        $question = $this->question(['media_path' => 'diagrams/lens "1".svg'], ['A' => 'x', 'B' => 'y']);
        QuestionOption::query()->where('question_id', $question->id)->where('key', 'A')->update(['media_path' => '/diagrams/a.svg']);
        $html = QuestionPreview::html($question->refresh()->load('options', 'passage'));

        $this->assertStringContainsString('src="'.asset('storage/diagrams/lens &quot;1&quot;.svg').'"', $html);
        $this->assertStringContainsString('src="'.asset('storage/diagrams/a.svg').'" alt="Picture for option A"', $html);
    }

    public function test_passage_questions_show_the_passage_first(): void
    {
        $passage = Passage::query()->create(['uid' => 'p-1', 'title' => 'Rain & rivers', 'body' => 'Water <i>flows</i>.']);
        $html = QuestionPreview::html($this->question(
            ['type' => QuestionType::PassageChoice, 'passage_id' => $passage->id, 'stem' => 'What flows?'],
            ['A' => 'Water', 'B' => 'Rock'],
        ));

        $this->assertStringContainsString('Rain &amp; rivers', $html);
        $this->assertStringContainsString('Water &lt;i&gt;flows&lt;/i&gt;.', $html);
        $this->assertLessThan(strpos($html, 'What flows?'), strpos($html, 'Rain &amp; rivers'));
    }

    public function test_numeric_and_fill_in_show_the_answer(): void
    {
        $numeric = QuestionPreview::html($this->question(['type' => QuestionType::Numeric, 'answer_key' => ['value' => 2.5, 'tolerance' => 0.01, 'unit' => 's']]));
        $this->assertStringContainsString('Answer: </span>2.5 ± 0.01 s</div>', $numeric);

        $exact = QuestionPreview::html($this->question(['type' => QuestionType::Numeric, 'answer_key' => ['value' => 7]]));
        $this->assertStringContainsString('Answer: </span>7</div>', $exact);

        $fill = QuestionPreview::html($this->question(['type' => QuestionType::FillIn, 'answer_key' => ['accepted' => ['ion', '<ion>']]]));
        $this->assertStringContainsString('Answer: </span>ion · &lt;ion&gt;</div>', $fill);
    }

    public function test_matching_shows_right_pairs_and_unused_items(): void
    {
        $html = QuestionPreview::html($this->question(
            ['type' => QuestionType::Matching, 'answer_key' => ['pairs' => ['L1' => 'R2', 'L2' => 'r1']]],
            ['L1' => ['left', 'farmer : plough'], 'L2' => ['left', 'brave : courageous'], 'R1' => ['right', 'Synonyms'], 'R2' => ['right', 'Worker and tool'], 'R3' => ['right', 'Young and adult']],
        ));

        $this->assertStringContainsString('farmer : plough → Worker and tool', $html);
        $this->assertStringContainsString('brave : courageous → Synonyms', $html);
        $this->assertStringContainsString('Not used: </span>Young and adult', $html);
        $this->assertStringNotContainsString('Not used: </span>Synonyms', $html);
    }

    public function test_ordering_shows_the_right_order(): void
    {
        $html = QuestionPreview::html($this->question(
            ['type' => QuestionType::Ordering, 'answer_key' => ['order' => ['B', 'C', 'A']]],
            ['A' => 'Region', 'B' => 'Village', 'C' => 'Zone'],
        ));

        $this->assertStringContainsString('<ol class="gp-order"><li>Village</li><li>Zone</li><li>Region</li></ol>', $html);
    }

    public function test_a_key_without_an_option_is_shown_escaped_not_dropped(): void
    {
        $html = QuestionPreview::html($this->question(
            ['type' => QuestionType::Ordering, 'answer_key' => ['order' => ['A', '<Z>']]],
            ['A' => 'Region'],
        ));

        $this->assertStringContainsString('<li>&lt;Z&gt;</li>', $html);
    }

    public function test_every_question_type_has_a_preview(): void
    {
        foreach (QuestionType::cases() as $type) {
            $html = QuestionPreview::html($this->question(['type' => $type, 'answer_key' => []], ['A' => 'x']));
            $this->assertStringStartsWith('<div class="guul-preview', $html, $type->value);
            $this->assertStringContainsString('Explanation:', $html, $type->value);
        }
    }
}
