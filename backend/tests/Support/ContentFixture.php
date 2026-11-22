<?php

namespace Tests\Support;

use App\Content\ContentFolder;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a throw-away content folder for tests.
 */
final class ContentFixture
{
    public readonly string $root;

    /** @var array<string, mixed> */
    public array $curriculum;

    /** @var array<string, array<string, mixed>> file => chapter data */
    public array $chapters = [];

    /** @var array<string, array<string, mixed>> mock file name => mock data */
    public array $mocks = [];

    public function __construct()
    {
        $this->root = sys_get_temp_dir().'/guul12-content-'.Str::random(10);
        mkdir($this->root.'/grade12/physics', 0777, true);
        $this->curriculum = [
            'grade' => 12,
            'subjects' => [[
                'code' => 'physics',
                'textbook_url' => 'https://elearn.moe.gov.et/storage/G12-Physics.pdf',
                'units' => [[
                    'number' => 2,
                    'title' => 'Two-dimensional motion',
                    'chapters' => [
                        ['number' => 1, 'title' => 'Projectile motion', 'file' => 'physics/u2-c1.yaml'],
                        ['number' => 2, 'title' => 'Circular motion'],
                    ],
                ]],
            ]],
        ];
        $this->chapters['physics/u2-c1.yaml'] = self::fullChapter();
    }

    /**
     * One question of every type, with the friendly answer format.
     *
     * @return array<string, mixed>
     */
    public static function fullChapter(): array
    {
        $p = 'g12-physics-2-1-';

        return [
            'summary' => [
                'key_points' => ['A projectile has constant horizontal velocity.', 'Vertical motion has acceleration $g$.'],
                'formulas' => [['label' => 'Range', 'latex' => 'R = \frac{v^2 \sin 2\theta}{g}']],
            ],
            'solved_exercises' => [[
                'uid' => $p.'se01',
                'problem' => 'A ball is thrown horizontally at 5 m/s from 20 m. How far does it land?',
                'steps' => ['Time: $t = \sqrt{2h/g} = 2$ s', 'Distance: $x = v t = 10$ m'],
                'answer' => '10 m',
            ]],
            'flashcards' => [['uid' => $p.'fc01', 'term' => 'Projectile', 'definition' => 'An object moving only under gravity after launch.']],
            'passages' => [['uid' => $p.'ps01', 'title' => 'Launch day', 'body' => 'A student kicks a ball at 30 degrees above the ground.']],
            'questions' => [
                ['uid' => $p.'q01', 'type' => 'single_choice', 'difficulty' => 2, 'stem' => 'Which quantity stays constant for a projectile (no air)?',
                    'options' => ['A' => 'Vertical velocity', 'B' => 'Horizontal velocity', 'C' => 'Speed', 'D' => 'Height'],
                    'answer' => 'B', 'explanation' => 'No horizontal force acts, so horizontal velocity does not change.'],
                ['uid' => $p.'q02', 'type' => 'true_false', 'difficulty' => 1, 'stem' => 'At the top of its path a projectile has zero speed.',
                    'answer' => false, 'explanation' => 'Vertical velocity is zero at the top, but the horizontal velocity remains.'],
                ['uid' => $p.'q03', 'type' => 'multiple_answers', 'difficulty' => 3, 'stem' => 'Which are vectors?',
                    'options' => ['A' => 'Velocity', 'B' => 'Mass', 'C' => 'Acceleration', 'D' => 'Time'],
                    'answer' => ['A', 'C'], 'explanation' => 'Velocity and acceleration have direction; mass and time do not.'],
                ['uid' => $p.'q04', 'type' => 'numeric', 'difficulty' => 2, 'stem' => 'A stone falls from 45 m ($g = 10$). Time to fall, in seconds?',
                    'answer' => ['value' => 3, 'tolerance' => 0, 'unit' => 's'], 'explanation' => '$t = \sqrt{2h/g} = \sqrt{9} = 3$ s.'],
                ['uid' => $p.'q05', 'type' => 'fill_in', 'difficulty' => 1, 'stem' => 'The path of a projectile is a ____.',
                    'answer' => ['parabola', 'parabolic curve'], 'explanation' => 'Constant horizontal velocity plus constant acceleration gives a parabola.'],
                ['uid' => $p.'q06', 'type' => 'matching', 'difficulty' => 2, 'stem' => 'Match each quantity with its unit.',
                    'left' => ['L1' => 'Velocity', 'L2' => 'Acceleration'], 'right' => ['R1' => 'm/s²', 'R2' => 'm/s'],
                    'answer' => ['L1' => 'R2', 'L2' => 'R1'], 'explanation' => 'Velocity is metres per second; acceleration is metres per second squared.'],
                ['uid' => $p.'q07', 'type' => 'ordering', 'difficulty' => 2, 'stem' => 'Put the steps for finding the range in order.',
                    'items' => ['A' => 'Find the time of flight', 'B' => 'Split the velocity into parts', 'C' => 'Multiply by horizontal velocity'],
                    'answer' => ['B', 'A', 'C'], 'explanation' => 'Resolve the velocity, use the vertical part for time, then x = v t.'],
                ['uid' => $p.'q08', 'type' => 'passage_choice', 'difficulty' => 2, 'passage' => $p.'ps01', 'stem' => 'At what angle is the ball kicked?',
                    'options' => ['A' => '30°', 'B' => '45°', 'C' => '60°'], 'answer' => 'A', 'explanation' => 'The passage says 30 degrees above the ground.'],
            ],
        ];
    }

    /**
     * Writes the folder. Returns its path.
     */
    public function write(): string
    {
        file_put_contents($this->root.'/grade12/curriculum.yaml', Yaml::dump($this->curriculum, 10, 2));
        foreach ($this->chapters as $file => $data) {
            file_put_contents($this->root.'/grade12/'.$file, Yaml::dump($data, 10, 2));
        }
        $mockDir = $this->root.'/grade12/mocks';
        foreach (glob($mockDir.'/*.yaml') ?: [] as $old) {
            unlink($old);
        }
        if ($this->mocks !== [] && ! is_dir($mockDir)) {
            mkdir($mockDir);
        }
        foreach ($this->mocks as $name => $data) {
            file_put_contents($mockDir.'/'.$name, Yaml::dump($data, 10, 2));
        }

        return $this->root;
    }

    /**
     * Adds single-choice questions g12-physics-2-1-q101, q102, … (after any added before)
     * with the given answer letters.
     *
     * @param  list<string>  $letters
     * @return list<string> their uids
     */
    public function addChoiceQuestions(array $letters, string $file = 'physics/u2-c1.yaml'): array
    {
        $uids = [];
        $first = 101 + count(array_filter(
            $this->chapters[$file]['questions'],
            fn (array $q) => preg_match('/-q1\d\d$/', $q['uid']) === 1,
        ));
        foreach ($letters as $i => $letter) {
            $n = $first + $i;
            $uid = "g12-physics-2-1-q{$n}";
            $this->chapters[$file]['questions'][] = [
                'uid' => $uid, 'type' => 'single_choice', 'difficulty' => 2,
                'stem' => "Extra question {$n}: what is {$n} + 1?",
                'options' => array_combine(['A', 'B', 'C', 'D'], array_map(
                    fn (string $l) => (string) ($l === $letter ? $n + 1 : $n + 10 + ord($l)),
                    ['A', 'B', 'C', 'D'],
                )),
                'answer' => $letter, 'explanation' => "Adding one to {$n} gives ".($n + 1).'.',
            ];
            $uids[] = $uid;
        }

        return $uids;
    }

    /**
     * Writes (or, with null, removes) the built summary PDF of a chapter file.
     */
    public function writePdf(string $file, ?string $bytes): void
    {
        $path = $this->root.'/pdf/grade12/'.substr($file, 0, -strlen('.yaml')).'.pdf';
        if ($bytes === null) {
            @unlink($path);

            return;
        }
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $bytes);
    }

    /**
     * Writes an independent-solve file for a chapter. By default every answer equals the key;
     * pass overrides (uid suffix => answer) to disagree.
     *
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $skip  uid suffixes with no second solve
     */
    public function writeReview(string $file, array $overrides = [], array $skip = [], string $reviewer = 'reviewer-agent'): void
    {
        $this->write();
        $bundle = ContentFolder::validator()->load($this->root);
        $answers = [];
        foreach ($bundle['curricula'][0]->subjects as $s) {
            foreach ($s->units as $u) {
                foreach ($u->chapters as $c) {
                    if ($c->file !== $file) {
                        continue;
                    }
                    $raw = [];
                    foreach ($this->chapters[$file]['questions'] as $q) {
                        // A solver writes one answer; for fill-in that is one of the accepted texts.
                        $raw[$q['uid']] = $q['type'] === 'fill_in' ? $q['answer'][0] : $q['answer'];
                    }
                    foreach ($c->questions as $q) {
                        $suffix = substr($q->uid, strrpos($q->uid, '-') + 1);
                        if (in_array($suffix, $skip, true)) {
                            continue;
                        }
                        $answer = array_key_exists($suffix, $overrides) ? $overrides[$suffix] : $raw[$q->uid];
                        $answers[$q->uid] = ['hash' => $q->reviewHash($c->passage($q->passageUid)), 'answer' => $answer];
                    }
                }
            }
        }
        $reviewFile = $this->root.'/grade12/'.str_replace('.yaml', '.review.yaml', $file);
        file_put_contents($reviewFile, Yaml::dump(['reviewer' => $reviewer, 'answers' => $answers], 10, 2));
    }

    public function delete(): void
    {
        if (! is_dir($this->root)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }
}
