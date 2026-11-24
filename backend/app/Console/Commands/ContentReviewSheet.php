<?php

namespace App\Console\Commands;

use App\Content\ChapterDef;
use App\Content\ContentFolder;
use App\Content\ContentInvalid;
use App\Content\OptionDef;
use App\Enums\QuestionType;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes a question sheet WITHOUT answers or explanations for the independent second solve.
 * The reviewer answers it and saves <chapter>.review.yaml next to the chapter file.
 */
class ContentReviewSheet extends Command
{
    protected $signature = 'content:review-sheet {file : chapter file, e.g. grade12/physics/u2-c1.yaml} {--out= : where to write the sheet} {--path= : content folder}';

    protected $description = 'Make a keyless question sheet for the independent second solve';

    public function handle(): int
    {
        $root = ContentFolder::path($this->option('path') !== null ? (string) $this->option('path') : null);

        try {
            $bundle = ContentFolder::validator()->load($root);
        } catch (ContentInvalid $e) {
            foreach ($e->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $wanted = (string) $this->argument('file');
        $chapter = null;
        foreach ($bundle['curricula'] as $curriculum) {
            foreach ($curriculum->subjects as $subject) {
                foreach ($subject->units as $unit) {
                    foreach ($unit->chapters as $c) {
                        if ($c->file !== null && "grade{$curriculum->grade}/{$c->file}" === $wanted) {
                            $chapter = $c;
                        }
                    }
                }
            }
        }
        if (! $chapter instanceof ChapterDef) {
            $this->error("No chapter uses the file {$wanted}");

            return self::FAILURE;
        }

        $sheet = [
            'instructions' => 'Solve every question yourself, by hand, without a calculator and without opening the chapter file. '
                .'Write your answers in a review file: reviewer: <your-name>, answers: { <uid>: { hash: <hash>, answer: <answer>, note: <optional> } }. '
                .'Answer formats: single_choice/passage_choice/image_choice: a letter; true_false: true or false; multiple_answers: a list of letters; '
                .'numeric: a number; fill_in: a short text; matching: { L1: R2, ... }; ordering: a list of item keys in order. '
                .'If a question is wrong, unclear or has two right answers, still answer and say why in note.',
            'chapter' => $chapter->code,
            'questions' => array_map(function ($q) use ($chapter): array {
                $passage = $chapter->passage($q->passageUid);
                $row = ['uid' => $q->uid, 'hash' => $q->reviewHash($passage), 'type' => $q->type->value, 'stem' => $q->stem];
                if ($passage !== null) {
                    $row['passage'] = $passage->title."\n\n".$passage->body;
                }
                if ($q->media !== null) {
                    $row['media'] = $q->media;
                }
                $texts = fn (?string $side) => array_column(
                    array_map(fn (OptionDef $o) => [$o->key, $o->body], array_values(array_filter($q->options, fn (OptionDef $o) => $o->side === $side))),
                    1, 0,
                );
                match ($q->type) {
                    QuestionType::Matching => [$row['left'], $row['right']] = [$texts('left'), $texts('right')],
                    QuestionType::Ordering => $row['items'] = $texts(null),
                    QuestionType::Numeric => isset($q->answerKey['unit']) ? $row['unit'] = $q->answerKey['unit'] : null,
                    QuestionType::FillIn, QuestionType::TrueFalse => null,
                    default => $row['options'] = $texts(null),
                };

                return $row;
            }, $chapter->questions),
        ];

        $yaml = Yaml::dump($sheet, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, $yaml);
            $this->info("Wrote {$out} (".count($chapter->questions).' questions)');
        } else {
            $this->line($yaml);
        }

        return self::SUCCESS;
    }
}
