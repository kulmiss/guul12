<?php

namespace App\Content;

use App\Enums\QuestionType;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the content folder (YAML) and runs every automatic check of the quality gate.
 * Nothing is imported unless the whole folder is valid.
 *
 * Layout:
 *   content/grade12/curriculum.yaml          subjects → units → chapters (+ file of each chapter)
 *   content/grade12/<subject>/<file>.yaml     one chapter: summary, exercises, flashcards, passages, questions
 *   content/grade12/<subject>/<file>.review.yaml   the independent second solve of that chapter
 */
final class ContentValidator
{
    public const STREAMS = ['natural', 'social'];

    private const CHOICE_KEYS = ['A', 'B', 'C', 'D', 'E', 'F'];

    private const MAX_STEM = 2000;

    private const MAX_OPTION = 400;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, string> uid => where it was first seen */
    private array $uids = [];

    /** @var array<string, string> normalised stem => uid */
    private array $stems = [];

    /**
     * @param  list<string>  $subjectCodes  subjects that exist in the database
     */
    public function __construct(private readonly array $subjectCodes) {}

    /**
     * @return array{curricula: list<CurriculumDef>, reviews: array<string, list<ReviewDef>>}
     *
     * @throws ContentInvalid
     */
    public function load(string $root): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->uids = [];
        $this->stems = [];

        if (! is_dir($root)) {
            throw new ContentInvalid(["Content folder not found: {$root}"], []);
        }

        $curricula = [];
        $reviews = [];
        $files = glob(rtrim($root, '/').'/grade*/curriculum.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            $curriculum = $this->readCurriculum($file, dirname($file));
            if ($curriculum !== null) {
                $curricula[] = $curriculum;
                foreach ($this->readReviews(dirname($file), $curriculum) as $uid => $list) {
                    $reviews[$uid] = $list;
                }
            }
        }

        $this->failIfErrors();

        return ['curricula' => $curricula, 'reviews' => $reviews];
    }

    /**
     * @throws ContentInvalid
     */
    private function failIfErrors(): void
    {
        if ($this->errors !== []) {
            throw new ContentInvalid($this->errors, $this->warnings);
        }
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    // ---- files ------------------------------------------------------------------------------

    /**
     * @return array<mixed>|null
     */
    private function parse(string $file): ?array
    {
        try {
            $data = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            $this->errors[] = basename(dirname($file)).'/'.basename($file).': '.$e->getMessage();

            return null;
        }

        if (! is_array($data)) {
            $this->errors[] = basename($file).': must be a YAML map';

            return null;
        }

        return $data;
    }

    private function readCurriculum(string $file, string $dir): ?CurriculumDef
    {
        $data = $this->parse($file);
        if ($data === null) {
            return null;
        }
        $where = basename($dir).'/curriculum.yaml';
        $grade = $data['grade'] ?? null;
        if (! is_int($grade) || $grade < 9 || $grade > 12) {
            $this->errors[] = "{$where}: grade must be 9–12";

            return null;
        }

        $subjects = [];
        $seenSubjects = [];
        foreach ($this->listOf($data['subjects'] ?? null, "{$where}: subjects") as $i => $s) {
            $at = "{$where}: subjects[{$i}]";
            $code = $this->str($s, 'code', $at, 48);
            if ($code === null) {
                continue;
            }
            if (! in_array($code, $this->subjectCodes, true)) {
                $this->errors[] = "{$at}: unknown subject '{$code}'";

                continue;
            }
            if (isset($seenSubjects[$code])) {
                $this->errors[] = "{$at}: subject '{$code}' listed twice";

                continue;
            }
            $seenSubjects[$code] = true;
            $url = $this->optionalStr($s, 'textbook_url', $at, 512);
            if ($url !== null && ! str_starts_with($url, 'https://')) {
                $this->errors[] = "{$at}: textbook_url must start with https://";
            }

            $units = [];
            $unitKeys = [];
            foreach ($this->listOf($s['units'] ?? null, "{$at}.units") as $j => $u) {
                $uat = "{$at}.units[{$j}]";
                $number = $this->int($u, 'number', $uat, 1, 99);
                $title = $this->str($u, 'title', $uat, 191);
                $stream = $this->optionalStr($u, 'stream', $uat, 16);
                if ($stream !== null && ! in_array($stream, self::STREAMS, true)) {
                    $this->errors[] = "{$uat}: stream must be natural, social or empty";
                }
                if ($number === null || $title === null) {
                    continue;
                }
                $unitKey = $number.'/'.($stream ?? '');
                if (isset($unitKeys[$unitKey])) {
                    $this->errors[] = "{$uat}: unit {$number} listed twice";

                    continue;
                }
                $unitKeys[$unitKey] = true;

                $chapters = [];
                $chapterNumbers = [];
                foreach ($this->listOf($u['chapters'] ?? null, "{$uat}.chapters") as $k => $c) {
                    $cat = "{$uat}.chapters[{$k}]";
                    $cn = $this->int($c, 'number', $cat, 1, 99);
                    $ct = $this->str($c, 'title', $cat, 191);
                    $cfile = $this->optionalStr($c, 'file', $cat, 191);
                    if ($cn === null || $ct === null) {
                        continue;
                    }
                    if (isset($chapterNumbers[$cn])) {
                        $this->errors[] = "{$cat}: chapter {$cn} listed twice";

                        continue;
                    }
                    $chapterNumbers[$cn] = true;
                    $code12 = "g{$grade}-{$code}-".($stream !== null ? "{$stream}-" : '')."{$number}-{$cn}";
                    $chapters[] = $this->readChapter($dir, $code12, $cn, $ct, $cfile);
                }
                $units[] = new UnitDef($number, $title, $stream, $chapters);
            }
            $subjects[] = new SubjectDef($code, $url, $units);
        }

        return new CurriculumDef($grade, $subjects);
    }

    private function readChapter(string $dir, string $code, int $number, string $title, ?string $file): ChapterDef
    {
        if ($file === null) {
            // Planned chapter, content not written yet: it appears in the catalog without questions.
            return new ChapterDef($code, $number, $title, null, null, [], [], [], []);
        }
        if (str_contains($file, '..') || ! str_ends_with($file, '.yaml') || str_ends_with($file, '.review.yaml')) {
            $this->errors[] = "{$code}: bad file name '{$file}'";

            return new ChapterDef($code, $number, $title, $file, null, [], [], [], []);
        }
        $path = $dir.'/'.$file;
        if (! is_file($path)) {
            $this->errors[] = "{$code}: file not found: {$file}";

            return new ChapterDef($code, $number, $title, $file, null, [], [], [], []);
        }
        $data = $this->parse($path) ?? [];
        $at = $file;

        $summary = null;
        if (isset($data['summary'])) {
            $s = $data['summary'];
            if (! is_array($s)) {
                $this->errors[] = "{$at}: summary must be a map";
            } else {
                $points = [];
                foreach ($this->listOf($s['key_points'] ?? [], "{$at}: summary.key_points") as $i => $p) {
                    if (! is_string($p) || trim($p) === '') {
                        $this->errors[] = "{$at}: summary.key_points[{$i}] must be text";

                        continue;
                    }
                    $this->checkText($p, "{$at}: summary.key_points[{$i}]");
                    $points[] = trim($p);
                }
                $formulas = [];
                foreach ($this->listOf($s['formulas'] ?? [], "{$at}: summary.formulas") as $i => $f) {
                    $label = $this->str($f, 'label', "{$at}: summary.formulas[{$i}]", 120);
                    $latex = $this->str($f, 'latex', "{$at}: summary.formulas[{$i}]", 500);
                    if ($label !== null && $latex !== null) {
                        if (str_contains($latex, '$')) {
                            $this->errors[] = "{$at}: summary.formulas[{$i}].latex must not contain \$ (it is already maths)";
                        }
                        $this->checkBraces($latex, "{$at}: summary.formulas[{$i}].latex");
                        $formulas[] = ['label' => $label, 'latex' => $latex];
                    }
                }
                $body = $this->optionalStr($s, 'body', "{$at}: summary", 20000);
                if ($body !== null) {
                    $this->checkText($body, "{$at}: summary.body");
                }
                if ($points === [] && $body === null) {
                    $this->errors[] = "{$at}: summary needs key_points or body";
                }
                $summary = new SummaryDef($points, $formulas, $body);
            }
        }

        $exercises = [];
        foreach ($this->listOf($data['solved_exercises'] ?? [], "{$at}: solved_exercises") as $i => $e) {
            $eat = "{$at}: solved_exercises[{$i}]";
            $uid = $this->uid($e, $eat, $code);
            $problem = $this->str($e, 'problem', $eat, self::MAX_STEM);
            $answer = $this->str($e, 'answer', $eat, 1000);
            $steps = [];
            foreach ($this->listOf($e['steps'] ?? null, "{$eat}.steps") as $k => $step) {
                if (! is_string($step) || trim($step) === '') {
                    $this->errors[] = "{$eat}.steps[{$k}] must be text";

                    continue;
                }
                $this->checkText($step, "{$eat}.steps[{$k}]");
                $steps[] = trim($step);
            }
            if ($steps === []) {
                $this->errors[] = "{$eat}: needs at least one step";
            }
            if ($uid !== null && $problem !== null && $answer !== null) {
                $this->checkText($problem, "{$eat}.problem");
                $this->checkText($answer, "{$eat}.answer");
                $exercises[] = new ExerciseDef($uid, $problem, $steps, $answer);
            }
        }

        $flashcards = [];
        foreach ($this->listOf($data['flashcards'] ?? [], "{$at}: flashcards") as $i => $f) {
            $fat = "{$at}: flashcards[{$i}]";
            $uid = $this->uid($f, $fat, $code);
            $term = $this->str($f, 'term', $fat, 191);
            $definition = $this->str($f, 'definition', $fat, 1000);
            if ($uid !== null && $term !== null && $definition !== null) {
                $this->checkText($term, "{$fat}.term");
                $this->checkText($definition, "{$fat}.definition");
                $flashcards[] = new FlashcardDef($uid, $term, $definition);
            }
        }

        $passages = [];
        foreach ($this->listOf($data['passages'] ?? [], "{$at}: passages") as $i => $p) {
            $pat = "{$at}: passages[{$i}]";
            $uid = $this->uid($p, $pat, $code);
            $ptitle = $this->str($p, 'title', $pat, 191);
            $body = $this->str($p, 'body', $pat, 12000);
            if ($uid !== null && $ptitle !== null && $body !== null) {
                $this->checkText($body, "{$pat}.body");
                $passages[] = new PassageDef($uid, $ptitle, $body);
            }
        }
        $passageUids = array_map(fn (PassageDef $p) => $p->uid, $passages);

        $questions = [];
        foreach ($this->listOf($data['questions'] ?? [], "{$at}: questions") as $i => $q) {
            $question = $this->readQuestion($q, "{$at}: questions[{$i}]", $code, $passageUids);
            if ($question !== null) {
                $questions[] = $question;
            }
        }
        $this->checkAnswerSpread($questions, $at);

        $unknown = array_diff(array_keys($data), ['summary', 'solved_exercises', 'flashcards', 'passages', 'questions']);
        foreach ($unknown as $key) {
            $this->errors[] = "{$at}: unknown section '{$key}'";
        }

        return new ChapterDef($code, $number, $title, $file, $summary, $exercises, $flashcards, $passages, $questions);
    }

    // ---- questions --------------------------------------------------------------------------

    /**
     * @param  list<string>  $passageUids
     */
    private function readQuestion(mixed $q, string $at, string $code, array $passageUids): ?QuestionDef
    {
        if (! is_array($q)) {
            $this->errors[] = "{$at}: must be a map";

            return null;
        }
        $uid = $this->uid($q, $at, $code);
        $at = $uid !== null ? "{$at} ({$uid})" : $at;
        $typeRaw = $this->str($q, 'type', $at, 32);
        $type = $typeRaw !== null ? QuestionType::tryFrom($typeRaw) : null;
        if ($typeRaw !== null && $type === null) {
            $this->errors[] = "{$at}: unknown type '{$typeRaw}'";
        }
        $difficulty = $this->int($q, 'difficulty', $at, 1, 5);
        $stem = $this->str($q, 'stem', $at, self::MAX_STEM);
        $explanation = $this->str($q, 'explanation', $at, 3000);
        $media = $this->optionalStr($q, 'media', $at, 255);
        $passage = $this->optionalStr($q, 'passage', $at, 64);
        $source = $this->optionalStr($q, 'source', $at, 191) ?? 'original';
        $tags = [];
        foreach ($this->listOf($q['tags'] ?? [], "{$at}.tags") as $t) {
            if (is_string($t) && preg_match('/^[a-z0-9-]{1,40}$/', $t) === 1) {
                $tags[] = $t;
            } else {
                $this->errors[] = "{$at}: tags must be short lowercase words";
            }
        }

        $allowed = ['uid', 'type', 'difficulty', 'stem', 'explanation', 'media', 'passage', 'source', 'tags', 'options', 'left', 'right', 'items', 'answer'];
        foreach (array_diff(array_keys($q), $allowed) as $key) {
            $this->errors[] = "{$at}: unknown field '{$key}'";
        }

        if ($uid === null || $type === null || $difficulty === null || $stem === null || $explanation === null) {
            return null;
        }
        $this->checkText($stem, "{$at}.stem");
        $this->checkText($explanation, "{$at}.explanation");
        if (mb_strlen(trim($explanation)) < 15) {
            $this->errors[] = "{$at}: explanation is too short to teach anything";
        }
        if ($source !== 'original' && preg_match('/^past_paper:[A-Za-z0-9 .\-]{4,60}$/', $source) !== 1) {
            $this->errors[] = "{$at}: source must be 'original' or 'past_paper:<paper name and year>'";
        }
        if ($passage !== null && ! in_array($passage, $passageUids, true)) {
            $this->errors[] = "{$at}: passage '{$passage}' is not in this chapter";
        }
        if ($type === QuestionType::PassageChoice && $passage === null) {
            $this->errors[] = "{$at}: passage_choice needs a passage";
        }
        if ($type === QuestionType::ImageChoice && $media === null) {
            $this->errors[] = "{$at}: image_choice needs media";
        }
        if ($media !== null && preg_match('#^media/[a-z0-9/_-]+\.(svg|png|webp)$#', $media) !== 1) {
            $this->errors[] = "{$at}: media must be like media/physics/vector-1.svg";
        }

        $built = $this->buildAnswer($type, $q, $at);
        if ($built === null) {
            return null;
        }
        [$options, $key] = $built;

        // Same question twice (anywhere in the bank) is an error.
        $norm = $this->normalise($stem).'|'.implode('|', array_map(fn (OptionDef $o) => $this->normalise($o->body), $options));
        if (isset($this->stems[$norm])) {
            $this->errors[] = "{$at}: duplicate of {$this->stems[$norm]}";
        } else {
            $this->stems[$norm] = $uid;
        }

        return new QuestionDef($uid, $type, $difficulty, trim($stem), $media, $passage, $options, $key, trim($explanation), $source, $tags);
    }

    /**
     * Turns the friendly "answer:" format into options + the scorer's key.
     *
     * @param  array<mixed>  $q
     * @return array{0: list<OptionDef>, 1: array<string, mixed>}|null
     */
    private function buildAnswer(QuestionType $type, array $q, string $at): ?array
    {
        $answer = $q['answer'] ?? null;

        switch ($type) {
            case QuestionType::SingleChoice:
            case QuestionType::ImageChoice:
            case QuestionType::PassageChoice:
                $options = $this->choiceOptions($q['options'] ?? null, $at, 3);
                if ($options === null) {
                    return null;
                }
                if (! is_string($answer) || ! $this->hasKey($options, $answer)) {
                    $this->errors[] = "{$at}: answer must be one of the option letters";

                    return null;
                }

                return [$options, ['correct' => $answer]];

            case QuestionType::TrueFalse:
                if (isset($q['options'])) {
                    $this->errors[] = "{$at}: true_false has no options";
                }
                if (! is_bool($answer)) {
                    $this->errors[] = "{$at}: answer must be true or false";

                    return null;
                }

                return [
                    [new OptionDef('TRUE', null, 'True'), new OptionDef('FALSE', null, 'False')],
                    ['correct' => $answer ? 'TRUE' : 'FALSE'],
                ];

            case QuestionType::MultipleAnswers:
                $options = $this->choiceOptions($q['options'] ?? null, $at, 4);
                if ($options === null) {
                    return null;
                }
                if (! is_array($answer) || ! array_is_list($answer) || $answer === []) {
                    $this->errors[] = "{$at}: answer must be a list of option letters";

                    return null;
                }
                $keys = [];
                foreach ($answer as $a) {
                    if (! is_string($a) || ! $this->hasKey($options, $a) || in_array($a, $keys, true)) {
                        $this->errors[] = "{$at}: answer has an unknown or repeated letter";

                        return null;
                    }
                    $keys[] = $a;
                }
                if (count($keys) < 2) {
                    $this->errors[] = "{$at}: multiple_answers needs at least 2 correct letters (use single_choice for one)";
                }
                sort($keys);

                return [$options, ['correct' => $keys]];

            case QuestionType::Numeric:
                if (isset($q['options'])) {
                    $this->errors[] = "{$at}: numeric has no options";
                }
                if (! is_array($answer) || ! (is_int($answer['value'] ?? null) || is_float($answer['value'] ?? null))) {
                    $this->errors[] = "{$at}: answer must be {value: <number>, tolerance: <number>, unit: <text>}";

                    return null;
                }
                $tolerance = $answer['tolerance'] ?? 0;
                if (! (is_int($tolerance) || is_float($tolerance)) || $tolerance < 0) {
                    $this->errors[] = "{$at}: tolerance must be a number ≥ 0";

                    return null;
                }
                $unit = $answer['unit'] ?? null;
                if ($unit !== null && (! is_string($unit) || mb_strlen($unit) > 16)) {
                    $this->errors[] = "{$at}: unit must be short text";

                    return null;
                }
                foreach (array_diff(array_keys($answer), ['value', 'tolerance', 'unit']) as $k) {
                    $this->errors[] = "{$at}: unknown answer field '{$k}'";
                }
                $key = ['value' => $answer['value'], 'tolerance' => $tolerance];
                if ($unit !== null) {
                    $key['unit'] = $unit;
                }

                return [[], $key];

            case QuestionType::FillIn:
                if (isset($q['options'])) {
                    $this->errors[] = "{$at}: fill_in has no options";
                }
                if (! is_array($answer) || ! array_is_list($answer) || $answer === []) {
                    $this->errors[] = "{$at}: answer must be a list of accepted answers";

                    return null;
                }
                $accepted = [];
                $seen = [];
                foreach ($answer as $a) {
                    if (! is_string($a) || trim($a) === '' || mb_strlen($a) > 60) {
                        $this->errors[] = "{$at}: each accepted answer must be short text";

                        return null;
                    }
                    $n = $this->normalise($a);
                    if (isset($seen[$n])) {
                        $this->errors[] = "{$at}: accepted answer '{$a}' repeats another";
                    }
                    $seen[$n] = true;
                    $accepted[] = trim($a);
                }

                return [[], ['accepted' => $accepted]];

            case QuestionType::Matching:
                $left = $this->keyedTexts($q['left'] ?? null, "{$at}.left", '/^L[1-8]$/');
                $right = $this->keyedTexts($q['right'] ?? null, "{$at}.right", '/^R[1-8]$/');
                if ($left === null || $right === null) {
                    return null;
                }
                if (count($left) < 2 || count($left) > 6 || count($right) < count($left) || count($right) > 8) {
                    $this->errors[] = "{$at}: matching needs 2–6 left items and at least as many right items";

                    return null;
                }
                if (! is_array($answer) || array_is_list($answer)) {
                    $this->errors[] = "{$at}: answer must map each left key to a right key";

                    return null;
                }
                $pairs = [];
                foreach ($left as $lk => $_) {
                    $rk = $answer[$lk] ?? null;
                    if (! is_string($rk) || ! array_key_exists($rk, $right)) {
                        $this->errors[] = "{$at}: answer for {$lk} must be a right key";

                        return null;
                    }
                    $pairs[$lk] = $rk;
                }
                if (count($answer) !== count($left) || count(array_unique($pairs)) !== count($pairs)) {
                    $this->errors[] = "{$at}: every left item needs its own, different right item";

                    return null;
                }
                $options = [];
                foreach ($left as $k => $v) {
                    $options[] = new OptionDef($k, 'left', $v);
                }
                foreach ($right as $k => $v) {
                    $options[] = new OptionDef($k, 'right', $v);
                }
                $this->checkDistinct(array_values($right), "{$at}.right");

                return [$options, ['pairs' => $pairs]];

            case QuestionType::Ordering:
                $items = $this->keyedTexts($q['items'] ?? null, "{$at}.items", '/^[A-H]$/');
                if ($items === null) {
                    return null;
                }
                if (count($items) < 3 || count($items) > 8) {
                    $this->errors[] = "{$at}: ordering needs 3–8 items";

                    return null;
                }
                if (! is_array($answer) || ! array_is_list($answer)) {
                    $this->errors[] = "{$at}: answer must list the item keys in the right order";

                    return null;
                }
                $order = array_values(array_filter($answer, 'is_string'));
                $sortedOrder = $order;
                sort($sortedOrder);
                $sortedKeys = array_map('strval', array_keys($items));
                sort($sortedKeys);
                if (count($order) !== count($answer) || $sortedOrder !== $sortedKeys) {
                    $this->errors[] = "{$at}: answer must use every item key exactly once";

                    return null;
                }
                $this->checkDistinct(array_values($items), "{$at}.items");
                $options = [];
                foreach ($items as $k => $v) {
                    $options[] = new OptionDef((string) $k, null, $v);
                }

                return [$options, ['order' => $order]];
        }
    }

    /**
     * @return list<OptionDef>|null
     */
    private function choiceOptions(mixed $raw, string $at, int $min): ?array
    {
        $texts = $this->keyedTexts($raw, "{$at}.options", '/^[A-F]$/');
        if ($texts === null) {
            return null;
        }
        $keys = array_map('strval', array_keys($texts));
        if (count($keys) < $min || count($keys) > 6) {
            $this->errors[] = "{$at}: needs {$min}–6 options";

            return null;
        }
        if ($keys !== array_slice(self::CHOICE_KEYS, 0, count($keys))) {
            $this->errors[] = "{$at}: option letters must be A, B, C… in order";

            return null;
        }
        $this->checkDistinct(array_values($texts), "{$at}.options");
        foreach ($texts as $text) {
            if (preg_match('/^(all|none) of the (above|options)\b/i', trim($text)) === 1) {
                $this->warnings[] = "{$at}: avoid 'all/none of the above'";
            }
        }

        return array_map(fn (string $k) => new OptionDef($k, null, $texts[$k]), $keys);
    }

    /**
     * @return array<string, string>|null
     */
    private function keyedTexts(mixed $raw, string $at, string $keyPattern): ?array
    {
        if (! is_array($raw) || $raw === [] || array_is_list($raw)) {
            $this->errors[] = "{$at}: must be a map like A: text";

            return null;
        }
        $out = [];
        foreach ($raw as $k => $v) {
            $k = (string) $k;
            if (preg_match($keyPattern, $k) !== 1) {
                $this->errors[] = "{$at}: bad key '{$k}'";

                return null;
            }
            if (! is_string($v) && ! is_int($v) && ! is_float($v)) {
                $this->errors[] = "{$at}.{$k}: must be text";

                return null;
            }
            $text = trim((string) $v);
            if ($text === '' || mb_strlen($text) > self::MAX_OPTION) {
                $this->errors[] = "{$at}.{$k}: must be 1–".self::MAX_OPTION.' characters';

                return null;
            }
            $this->checkText($text, "{$at}.{$k}");
            $out[$k] = $text;
        }

        return $out;
    }

    /**
     * @param  list<OptionDef>  $options
     */
    private function hasKey(array $options, string $key): bool
    {
        foreach ($options as $o) {
            if ($o->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $texts
     */
    private function checkDistinct(array $texts, string $at): void
    {
        $norm = array_map(fn (string $t) => $this->normalise($t), $texts);
        if (count(array_unique($norm)) !== count($norm)) {
            $this->errors[] = "{$at}: two items say the same thing";
        }
    }

    /**
     * The right answer must not sit on the same letter most of the time.
     *
     * @param  list<QuestionDef>  $questions
     */
    private function checkAnswerSpread(array $questions, string $at): void
    {
        $letters = [];
        foreach ($questions as $q) {
            if ($q->type === QuestionType::SingleChoice && is_string($q->answerKey['correct'] ?? null)) {
                $letters[] = $q->answerKey['correct'];
            }
        }
        if (count($letters) < 8) {
            return;
        }
        $counts = array_count_values($letters);
        arsort($counts);
        $top = (int) reset($counts);
        if ($top / count($letters) > 0.4) {
            $this->errors[] = "{$at}: the right answer is '".array_key_first($counts)."' in {$top} of ".count($letters).' single-choice questions; mix the letters';
        }
    }

    // ---- reviews ----------------------------------------------------------------------------

    /**
     * @return array<string, list<ReviewDef>>
     */
    private function readReviews(string $dir, CurriculumDef $curriculum): array
    {
        $known = [];
        foreach ($curriculum->subjects as $s) {
            foreach ($s->units as $u) {
                foreach ($u->chapters as $c) {
                    foreach ($c->questions as $q) {
                        $known[$q->uid] = true;
                    }
                }
            }
        }

        $out = [];
        $files = glob($dir.'/*/*.review.yaml') ?: [];
        sort($files);
        foreach ($files as $file) {
            $data = $this->parse($file);
            if ($data === null) {
                continue;
            }
            $at = basename(dirname($file)).'/'.basename($file);
            $reviewer = $data['reviewer'] ?? null;
            if (! is_string($reviewer) || preg_match('/^[a-z0-9-]{3,64}$/', $reviewer) !== 1) {
                $this->errors[] = "{$at}: reviewer must be a short name like reviewer-agent";

                continue;
            }
            $answers = $data['answers'] ?? null;
            if (! is_array($answers) || array_is_list($answers)) {
                $this->errors[] = "{$at}: answers must map question uid → {hash, answer}";

                continue;
            }
            foreach ($answers as $uid => $a) {
                $uid = (string) $uid;
                if (! isset($known[$uid])) {
                    $this->warnings[] = "{$at}: {$uid} is not a question any more (ignored)";

                    continue;
                }
                if (! is_array($a) || ! is_string($a['hash'] ?? null) || ! array_key_exists('answer', $a)) {
                    $this->errors[] = "{$at}: {$uid} needs hash and answer";

                    continue;
                }
                $note = $a['note'] ?? null;
                $out[$uid][] = new ReviewDef($uid, $reviewer, $a['hash'], $a['answer'], is_string($note) ? $note : null);
            }
        }

        return $out;
    }

    // ---- small readers ----------------------------------------------------------------------

    /**
     * @return array<int, mixed>
     */
    private function listOf(mixed $value, string $at): array
    {
        if ($value === null) {
            $this->errors[] = "{$at}: missing";

            return [];
        }
        if (! is_array($value) || ! array_is_list($value)) {
            $this->errors[] = "{$at}: must be a list";

            return [];
        }

        return $value;
    }

    private function str(mixed $map, string $key, string $at, int $max): ?string
    {
        $value = is_array($map) ? ($map[$key] ?? null) : null;
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (! is_string($value) || trim($value) === '') {
            $this->errors[] = "{$at}: {$key} is missing";

            return null;
        }
        if (mb_strlen($value) > $max) {
            $this->errors[] = "{$at}: {$key} is longer than {$max} characters";

            return null;
        }

        return trim($value);
    }

    private function optionalStr(mixed $map, string $key, string $at, int $max): ?string
    {
        if (! is_array($map) || ! isset($map[$key])) {
            return null;
        }

        return $this->str($map, $key, $at, $max);
    }

    private function int(mixed $map, string $key, string $at, int $min, int $max): ?int
    {
        $value = is_array($map) ? ($map[$key] ?? null) : null;
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->errors[] = "{$at}: {$key} must be a whole number {$min}–{$max}";

            return null;
        }

        return $value;
    }

    private function uid(mixed $map, string $at, string $chapterCode): ?string
    {
        $uid = $this->str($map, 'uid', $at, 64);
        if ($uid === null) {
            return null;
        }
        if (! str_starts_with($uid, $chapterCode.'-') || preg_match('/^[a-z0-9-]+$/', $uid) !== 1) {
            $this->errors[] = "{$at}: uid '{$uid}' must start with '{$chapterCode}-' and use a–z, 0–9, -";

            return null;
        }
        if (isset($this->uids[$uid])) {
            $this->errors[] = "{$at}: uid '{$uid}' is already used at {$this->uids[$uid]}";

            return null;
        }
        $this->uids[$uid] = $at;

        return $uid;
    }

    /**
     * Text checks: balanced $…$ maths, balanced braces inside maths, no HTML.
     */
    private function checkText(string $text, string $at): void
    {
        if (preg_match('/<\s*(script|iframe|img|a)\b/i', $text) === 1) {
            $this->errors[] = "{$at}: HTML is not allowed";
        }
        $unescaped = preg_replace('/\\\\\$/', '', $text) ?? $text;
        if (substr_count($unescaped, '$') % 2 !== 0) {
            $this->errors[] = "{$at}: unbalanced \$ (write \\\$ for a dollar sign)";

            return;
        }
        if (preg_match_all('/\$\$(.+?)\$\$|\$(.+?)\$/s', $unescaped, $m) > 0) {
            foreach ($m[0] as $segment) {
                $this->checkBraces($segment, $at);
            }
        }
    }

    private function checkBraces(string $latex, string $at): void
    {
        $depth = 0;
        $clean = preg_replace('/\\\\[{}]/', '', $latex) ?? $latex;
        foreach (str_split($clean) as $ch) {
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth < 0) {
                    break;
                }
            }
        }
        if ($depth !== 0) {
            $this->errors[] = "{$at}: unbalanced { } in maths";
        }
    }

    private function normalise(string $text): string
    {
        $lower = mb_strtolower($text);
        $lower = preg_replace('/[^\p{L}\p{N}$\\\\^_{}+\-*\/=.<>]+/u', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/', ' ', $lower) ?? $lower);
    }
}
