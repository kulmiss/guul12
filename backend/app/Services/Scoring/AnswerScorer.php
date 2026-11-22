<?php

namespace App\Services\Scoring;

use App\Enums\QuestionType;

/**
 * Decides whether a student's response matches a question's answer key.
 * The server is the only judge: the app's own check is for instant feedback only.
 *
 * Response shapes sent by the app:
 * - single-key types: "B"
 * - multiple_answers: ["A", "C"]
 * - numeric: 2.5 or "2.5" (also "2,5" and simple fractions like "5/2")
 * - fill_in: "photosynthesis"
 * - matching: {"L1": "R3", "L2": "R1"}
 * - ordering: ["C", "A", "B"]
 * - skipped: null
 */
class AnswerScorer
{
    /**
     * @param  array<string, mixed>  $key
     */
    public function isCorrect(QuestionType $type, array $key, mixed $response): bool
    {
        if ($response === null) {
            return false;
        }

        return match ($type) {
            QuestionType::SingleChoice,
            QuestionType::TrueFalse,
            QuestionType::ImageChoice,
            QuestionType::PassageChoice => $this->scoreSingle($key, $response),
            QuestionType::MultipleAnswers => $this->scoreMultiple($key, $response),
            QuestionType::Numeric => $this->scoreNumeric($key, $response),
            QuestionType::FillIn => $this->scoreFillIn($key, $response),
            QuestionType::Matching => $this->scoreMatching($key, $response),
            QuestionType::Ordering => $this->scoreOrdering($key, $response),
        };
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreSingle(array $key, mixed $response): bool
    {
        return is_string($response)
            && is_string($key['correct'] ?? null)
            && strtoupper(trim($response)) === strtoupper($key['correct']);
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreMultiple(array $key, mixed $response): bool
    {
        $correct = $key['correct'] ?? null;

        if (! is_array($response) || ! is_array($correct) || ! array_is_list($response)) {
            return false;
        }

        $given = $this->normaliseKeyList($response);
        $expected = $this->normaliseKeyList($correct);

        // Compared as sets: order and repeats do not matter, a missing or extra key does.
        return $given !== null && $expected !== null && $expected !== [] && $given === $expected;
    }

    /**
     * @param  array<int|string, mixed>  $keys
     * @return list<string>|null
     */
    private function normaliseKeyList(array $keys): ?array
    {
        $out = [];

        foreach ($keys as $k) {
            if (! is_string($k)) {
                return null;
            }
            $out[] = strtoupper(trim($k));
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreNumeric(array $key, mixed $response): bool
    {
        $expected = $key['value'] ?? null;
        $tolerance = $key['tolerance'] ?? 0;

        if (! is_int($expected) && ! is_float($expected)) {
            return false;
        }
        if (! is_int($tolerance) && ! is_float($tolerance)) {
            return false;
        }

        $given = self::parseNumber($response);

        if ($given === null) {
            return false;
        }

        $epsilon = 1e-9 * max(1.0, abs((float) $expected));

        return abs($given - (float) $expected) <= abs((float) $tolerance) + $epsilon;
    }

    /**
     * Reads "2.5", "2,5", "-3", "5/2" and real numbers. Returns null for anything else.
     */
    public static function parseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = str_replace([' ', "\u{2212}"], ['', '-'], trim($value));

        if (preg_match('/^([+-]?\d+)\/(\d+)$/', $text, $m) === 1) {
            $denominator = (int) $m[2];

            return $denominator === 0 ? null : (int) $m[1] / $denominator;
        }

        // A single comma with no dot is a decimal comma.
        if (substr_count($text, ',') === 1 && ! str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/', $text) !== 1) {
            return null;
        }

        $number = (float) $text;

        return is_finite($number) ? $number : null;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreFillIn(array $key, mixed $response): bool
    {
        $accepted = $key['accepted'] ?? null;

        if (! is_string($response) || ! is_array($accepted)) {
            return false;
        }

        $given = self::normaliseText($response);

        if ($given === '') {
            return false;
        }

        foreach ($accepted as $answer) {
            if (is_string($answer) && self::normaliseText($answer) === $given) {
                return true;
            }
        }

        return false;
    }

    public static function normaliseText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return rtrim($text, ' .,;:!?');
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreMatching(array $key, mixed $response): bool
    {
        $pairs = $key['pairs'] ?? null;

        if (! is_array($response) || ! is_array($pairs) || $pairs === [] || (array_is_list($response) && $response !== [])) {
            return false;
        }

        if (count($response) !== count($pairs)) {
            return false;
        }

        foreach ($pairs as $left => $right) {
            $given = $response[$left] ?? null;

            if (! is_string($given) || ! is_string($right) || strtoupper(trim($given)) !== strtoupper($right)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function scoreOrdering(array $key, mixed $response): bool
    {
        $order = $key['order'] ?? null;

        if (! is_array($response) || ! is_array($order) || ! array_is_list($response) || count($response) !== count($order)) {
            return false;
        }

        foreach (array_values($order) as $i => $expected) {
            $given = $response[$i];

            if (! is_string($given) || ! is_string($expected) || strtoupper(trim($given)) !== strtoupper($expected)) {
                return false;
            }
        }

        return true;
    }
}
