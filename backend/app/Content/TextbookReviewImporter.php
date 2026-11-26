<?php

namespace App\Content;

use App\Models\TextbookReview;
use App\Models\Unit;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Imports the solved textbook review exercises: one file per unit, at
 * <folder>/grade<N>/<subject code>/review-u<unit number>.yaml.
 *
 * Every file is checked first; if any file has a problem nothing is written. Importing again
 * changes only what changed, and a unit whose file is gone loses its review exercise.
 * The folder is kept out of git (content/textbook/) while the repository is public.
 */
final class TextbookReviewImporter
{
    private const SOURCE_KEYS = ['book', 'unit', 'unit_title', 'exercise', 'pages', 'conventions', 'method'];

    private const ITEM_KEYS = ['ref', 'question', 'note', 'figure', 'table', 'book_error', 'steps', 'answer', 'parts'];

    private const PART_KEYS = ['ref', 'question', 'note', 'steps', 'answer'];

    /**
     * @param  list<string>  $subjectCodes
     */
    public function __construct(private readonly array $subjectCodes) {}

    /**
     * Checks every file and returns what would be stored, by unit key "grade|subject|unit".
     *
     * @return array<string, array{grade: int, subject: string, unit: int, book: string, pages: ?string, intro: ?string, items: list<array<string, mixed>>}>
     *
     * @throws ContentInvalid
     */
    public function load(string $folder): array
    {
        $errors = [];
        $reviews = [];
        if ($folder === '' || ! is_dir($folder)) {
            throw new ContentInvalid(["Textbook folder not found: {$folder}"], []);
        }

        $files = glob(rtrim($folder, '/').'/*/*/*') ?: [];
        sort($files);
        foreach ($files as $file) {
            $relative = substr($file, strlen(rtrim($folder, '/')) + 1);
            if (preg_match('#^grade(\d{1,2})/([a-z_]+)/review-u(\d{1,2})\.yaml$#', $relative, $m) !== 1) {
                $errors[] = "{$relative}: unexpected file (use grade<N>/<subject>/review-u<unit>.yaml)";

                continue;
            }
            [$grade, $subject, $unit] = [(int) $m[1], $m[2], (int) $m[3]];
            if (! in_array($subject, $this->subjectCodes, true)) {
                $errors[] = "{$relative}: unknown subject \"{$subject}\"";

                continue;
            }
            if (! in_array($grade, Unit::GRADES, true)) {
                $errors[] = "{$relative}: unknown grade {$grade}";

                continue;
            }

            try {
                $data = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (ParseException $e) {
                $errors[] = "{$relative}: not valid YAML ({$e->getMessage()})";

                continue;
            }

            $fileErrors = [];
            $review = $this->checkFile($data, $unit, $fileErrors);
            foreach ($fileErrors as $error) {
                $errors[] = "{$relative}: {$error}";
            }
            if ($review !== null && $fileErrors === []) {
                $reviews["{$grade}|{$subject}|{$unit}"] = ['grade' => $grade, 'subject' => $subject, 'unit' => $unit, ...$review];
            }
        }

        if ($files === []) {
            $errors[] = 'No review files found.';
        }
        if ($errors !== []) {
            throw new ContentInvalid($errors, []);
        }

        return $reviews;
    }

    /**
     * Writes the checked reviews. Every unit must already exist (run content:import first).
     *
     * @param  array<string, array{grade: int, subject: string, unit: int, book: string, pages: ?string, intro: ?string, items: list<array<string, mixed>>}>  $reviews
     *
     * @throws ContentInvalid
     */
    public function import(array $reviews): ImportReport
    {
        $report = new ImportReport;
        $targets = [];
        $errors = [];
        foreach ($reviews as $key => $review) {
            $unit = Unit::query()
                ->where('grade', $review['grade'])
                ->where('number', $review['unit'])
                ->where('is_active', true)
                ->whereHas('subject', fn ($q) => $q->where('code', $review['subject']))
                ->get();
            if ($unit->count() !== 1) {
                $errors[] = "Grade {$review['grade']} {$review['subject']} unit {$review['unit']}: ".
                    ($unit->isEmpty() ? 'no such unit in the database (run content:import first)' : 'more than one unit (one per stream); not supported yet');

                continue;
            }
            $targets[$key] = $unit->first();
        }
        if ($errors !== []) {
            throw new ContentInvalid($errors, []);
        }

        DB::transaction(function () use ($reviews, $targets, $report): void {
            $kept = [];
            foreach ($reviews as $key => $review) {
                /** @var Unit $unit */
                $unit = $targets[$key];
                $row = TextbookReview::query()->firstOrNew(['unit_id' => $unit->id]);
                $row->fill([
                    'book' => $review['book'],
                    'pages' => $review['pages'],
                    'intro' => $review['intro'],
                    'items' => $review['items'],
                    'question_count' => count($review['items']),
                ]);
                $report->count($row->exists ? ($row->isDirty() ? 'reviews_updated' : 'reviews_unchanged') : 'reviews_created');
                if (! $row->exists || $row->isDirty()) {
                    $row->save();
                }
                $kept[] = $row->id;
            }

            foreach (TextbookReview::query()->whereNotIn('id', $kept)->get() as $gone) {
                $gone->delete();
                $report->count('reviews_removed');
            }
        });

        return $report;
    }

    /**
     * @param  list<string>  $errors
     * @return array{book: string, pages: ?string, intro: ?string, items: list<array<string, mixed>>}|null
     */
    private function checkFile(mixed $data, int $unit, array &$errors): ?array
    {
        if (! is_array($data)) {
            $errors[] = 'the file must be a map with "source" and "items"';

            return null;
        }
        $unknown = array_diff(array_keys($data), ['source', 'items']);
        if ($unknown !== []) {
            $errors[] = 'unknown key(s): '.implode(', ', $unknown);
        }

        $source = $data['source'] ?? null;
        if (! is_array($source)) {
            $errors[] = 'missing "source"';

            return null;
        }
        $unknown = array_diff(array_keys($source), self::SOURCE_KEYS);
        if ($unknown !== []) {
            $errors[] = 'source: unknown key(s): '.implode(', ', $unknown);
        }
        $book = $source['book'] ?? null;
        if (! is_string($book) || trim($book) === '' || mb_strlen($book) > 160) {
            $errors[] = 'source.book: a text of 1–160 characters is needed';
        }
        if (($source['unit'] ?? null) !== $unit) {
            $errors[] = "source.unit must be {$unit} (the number in the file name)";
        }
        $pages = $source['pages'] ?? null;
        if ($pages !== null && (! is_string($pages) || mb_strlen($pages) > 20)) {
            $errors[] = 'source.pages: a short text is needed';
        }
        $intro = $source['conventions'] ?? $source['method'] ?? null;
        if ($intro !== null && ! is_string($intro)) {
            $errors[] = 'source.conventions/method must be text';
        }

        $items = $data['items'] ?? null;
        if (! is_array($items) || $items === [] || ! array_is_list($items)) {
            $errors[] = '"items" must be a non-empty list';

            return null;
        }

        $clean = [];
        $refs = [];
        foreach ($items as $i => $item) {
            $where = 'item '.($i + 1);
            $checked = $this->checkEntry($item, self::ITEM_KEYS, $where, $errors);
            if ($checked === null) {
                continue;
            }
            if (in_array($checked['ref'], $refs, true)) {
                $errors[] = "{$where}: ref \"{$checked['ref']}\" is used twice";
            }
            $refs[] = $checked['ref'];

            $bookError = $item['book_error'] ?? false;
            if (! is_bool($bookError)) {
                $errors[] = "{$where}: book_error must be true or false";
            }
            if (isset($item['figure']) && ! is_string($item['figure'])) {
                $errors[] = "{$where}: figure must be text";
            }
            if (isset($item['table']) && ! $this->isTable($item['table'])) {
                $errors[] = "{$where}: table must be a map of row name to a list of cells, all rows the same length";
            }

            $parts = [];
            if (isset($item['parts'])) {
                if (! is_array($item['parts']) || $item['parts'] === [] || ! array_is_list($item['parts'])) {
                    $errors[] = "{$where}: parts must be a non-empty list";
                } else {
                    foreach ($item['parts'] as $j => $part) {
                        $partWhere = "{$where} part ".($j + 1);
                        $p = $this->checkEntry($part, self::PART_KEYS, $partWhere, $errors);
                        if ($p === null) {
                            continue;
                        }
                        if ($p['answer'] === null && $p['steps'] === [] && $bookError !== true) {
                            $errors[] = "{$partWhere}: needs an answer or steps";
                        }
                        $parts[] = $p;
                    }
                }
            }

            if ($checked['answer'] === null && $parts === [] && $bookError !== true) {
                $errors[] = "{$where}: needs an answer (or parts with answers, or book_error: true)";
            }

            $clean[] = [
                ...$checked,
                'figure' => is_string($item['figure'] ?? null) ? $item['figure'] : null,
                'table' => $this->isTable($item['table'] ?? null) ? $item['table'] : null,
                'book_error' => $bookError === true,
                'parts' => $parts,
            ];
        }

        return [
            'book' => is_string($book) ? trim($book) : '',
            'pages' => is_string($pages) ? $pages : null,
            'intro' => is_string($intro) ? $intro : null,
            'items' => $clean,
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $errors
     * @return array{ref: string, question: string, note: ?string, steps: list<string>, answer: ?string}|null
     */
    private function checkEntry(mixed $entry, array $allowed, string $where, array &$errors): ?array
    {
        if (! is_array($entry)) {
            $errors[] = "{$where}: must be a map";

            return null;
        }
        $unknown = array_diff(array_keys($entry), $allowed);
        if ($unknown !== []) {
            $errors[] = "{$where}: unknown key(s): ".implode(', ', $unknown);
        }
        $ref = $entry['ref'] ?? null;
        if (! is_string($ref) && ! is_int($ref)) {
            $errors[] = "{$where}: ref is missing";

            return null;
        }
        $question = $entry['question'] ?? null;
        if (! is_string($question) || trim($question) === '') {
            $errors[] = "{$where}: question is missing";

            return null;
        }
        $steps = $entry['steps'] ?? [];
        if (! is_array($steps) || ! array_is_list($steps) || array_filter($steps, fn ($s): bool => ! is_string($s) || trim($s) === '') !== []) {
            $errors[] = "{$where}: steps must be a list of texts";
            $steps = [];
        }
        $answer = $entry['answer'] ?? null;
        if ($answer !== null && (! is_string($answer) || trim($answer) === '')) {
            $errors[] = "{$where}: answer must be text";
            $answer = null;
        }
        $note = $entry['note'] ?? null;
        if ($note !== null && ! is_string($note)) {
            $errors[] = "{$where}: note must be text";
            $note = null;
        }

        /** @var list<string> $steps */
        return [
            'ref' => (string) $ref,
            'question' => trim($question),
            'note' => $note,
            'steps' => $steps,
            'answer' => $answer,
        ];
    }

    /**
     * A map of row name to a list of cells, every row the same length (as printed in the book).
     */
    private function isTable(mixed $table): bool
    {
        if (! is_array($table) || $table === [] || array_is_list($table)) {
            return false;
        }
        $length = null;
        foreach ($table as $cells) {
            if (! is_array($cells) || $cells === [] || ! array_is_list($cells) || array_filter($cells, fn ($c): bool => ! is_scalar($c)) !== []) {
                return false;
            }
            $length ??= count($cells);
            if (count($cells) !== $length) {
                return false;
            }
        }

        return true;
    }

    /**
     * An importer for the subjects the app knows (from the curriculum seeder).
     */
    public static function forKnownSubjects(): self
    {
        return new self(array_keys(CurriculumSeeder::SUBJECTS));
    }
}
