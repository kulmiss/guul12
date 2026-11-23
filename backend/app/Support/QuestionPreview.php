<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Collection;

/**
 * The question as students see it, for checkers in Nova: passage, text with maths (drawn by KaTeX in
 * the browser), picture or diagram, options with the right answer marked, and the explanation.
 * Everything a staff member typed is escaped; only our own markup is HTML.
 */
final class QuestionPreview
{
    public static function html(Question $question): string
    {
        $parts = [];

        if ($question->passage !== null) {
            $parts[] = '<div class="gp-passage">'
                .'<div class="gp-title">'.self::e($question->passage->title).'</div>'
                .MathText::html($question->passage->body).'</div>';
        }

        $parts[] = '<div class="gp-stem">'.MathText::html($question->stem).'</div>';

        if ($question->media_path !== null) {
            $parts[] = self::image($question->media_path, 'Picture for the question');
        }

        $options = $question->options->sortBy('sort')->values();
        $parts[] = match ($question->type) {
            QuestionType::Matching => self::matching($options, $question->answer_key),
            QuestionType::Ordering => self::ordering($options, $question->answer_key),
            QuestionType::Numeric => self::answer(self::numeric($question->answer_key)),
            QuestionType::FillIn => self::answer(implode(' · ', array_map('strval', (array) ($question->answer_key['accepted'] ?? [])))),
            default => self::choices($options, $question->answer_key),
        };

        $parts[] = '<div class="gp-explanation">'
            .'<span class="gp-label">Explanation: </span>'.MathText::html($question->explanation).'</div>';

        return '<div class="guul-preview">'.implode('', $parts).'</div>';
    }

    /**
     * @param  Collection<int, QuestionOption>  $options
     * @param  array<string, mixed>  $key
     */
    private static function choices(Collection $options, array $key): string
    {
        $correct = array_map(fn ($k): string => strtoupper(trim((string) $k)), (array) ($key['correct'] ?? []));
        $rows = '';
        foreach ($options as $option) {
            $right = in_array(strtoupper($option->key), $correct, true);
            $rows .= '<li class="gp-option'.($right ? ' gp-right' : '').'">'
                .'<span class="gp-key">'.self::e($option->key).'</span>'
                .'<span class="gp-body">'.MathText::html($option->body)
                .($option->media_path !== null ? self::image($option->media_path, 'Picture for option '.$option->key) : '').'</span>'
                .($right ? '<span class="gp-tick" title="Right answer">✓</span>' : '')
                .'</li>';
        }

        return '<ul class="gp-list">'.$rows.'</ul>';
    }

    /**
     * @param  Collection<int, QuestionOption>  $options
     * @param  array<string, mixed>  $key
     */
    private static function matching(Collection $options, array $key): string
    {
        $byKey = $options->keyBy(fn (QuestionOption $o): string => strtoupper($o->key));
        $used = [];
        $rows = '';
        foreach ((array) ($key['pairs'] ?? []) as $left => $right) {
            $used[] = strtoupper((string) $right);
            $rows .= '<li class="gp-option gp-right">'
                .self::body($byKey, (string) $left).' → '.self::body($byKey, (string) $right).'</li>';
        }
        $unused = $options->filter(fn (QuestionOption $o): bool => $o->side === QuestionOption::SIDE_RIGHT && ! in_array(strtoupper($o->key), $used, true));
        foreach ($unused as $option) {
            $rows .= '<li class="gp-option">'
                .'<span class="gp-muted">Not used: </span>'.MathText::html($option->body).'</li>';
        }

        return '<div class="gp-label">Right pairs</div><ul class="gp-list">'.$rows.'</ul>';
    }

    /**
     * @param  Collection<int, QuestionOption>  $options
     * @param  array<string, mixed>  $key
     */
    private static function ordering(Collection $options, array $key): string
    {
        $byKey = $options->keyBy(fn (QuestionOption $o): string => strtoupper($o->key));
        $rows = '';
        foreach ((array) ($key['order'] ?? []) as $item) {
            $rows .= '<li>'.self::body($byKey, (string) $item).'</li>';
        }

        return '<div class="gp-label">Right order</div><ol class="gp-order">'.$rows.'</ol>';
    }

    /** @param  array<string, mixed>  $key */
    private static function numeric(array $key): string
    {
        $value = is_numeric($key['value'] ?? null) ? (string) $key['value'] : '?';
        $tolerance = is_numeric($key['tolerance'] ?? null) && (float) $key['tolerance'] > 0 ? ' ± '.$key['tolerance'] : '';
        $unit = is_string($key['unit'] ?? null) && $key['unit'] !== '' ? ' '.$key['unit'] : '';

        return $value.$tolerance.$unit;
    }

    /**
     * The option's text for a key from the answer key; the bare key (escaped) if no option has it.
     *
     * @param  Collection<string, QuestionOption>  $byKey
     */
    private static function body(Collection $byKey, string $key): string
    {
        $option = $byKey->get(strtoupper(trim($key)));

        return $option instanceof QuestionOption ? MathText::html($option->body) : self::e($key);
    }

    private static function answer(string $text): string
    {
        return '<div class="gp-option gp-right">'
            .'<span class="gp-label">Answer: </span>'.self::e($text).'</div>';
    }

    private static function image(string $path, string $alt): string
    {
        return '<img src="'.self::e(asset('storage/'.ltrim($path, '/'))).'" alt="'.self::e($alt).'" class="gp-image" loading="lazy">';
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
