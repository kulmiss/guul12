<?php

namespace App\Console\Commands;

use App\Content\ContentFolder;
use App\Content\ContentInvalid;
use Illuminate\Console\Command;

/**
 * Runs every automatic check on the content files without touching the database (CI runs this).
 */
class ContentCheck extends Command
{
    protected $signature = 'content:check {path? : content folder (default: CONTENT_PATH)} {--min-questions=0 : warn when a written chapter has fewer questions}';

    protected $description = 'Check the content files (quality gate automatic checks)';

    public function handle(): int
    {
        $validator = ContentFolder::validator();

        try {
            $bundle = $validator->load(ContentFolder::path($this->argument('path')));
        } catch (ContentInvalid $e) {
            foreach ($e->warnings as $w) {
                $this->warn($w);
            }
            foreach ($e->errors as $error) {
                $this->error($error);
            }
            $this->error(count($e->errors).' error(s). Nothing may be imported until they are fixed.');

            return self::FAILURE;
        }

        foreach ($validator->warnings() as $w) {
            $this->warn($w);
        }

        $min = (int) $this->option('min-questions');
        $rows = [];
        $totals = ['chapters' => 0, 'written' => 0, 'questions' => 0, 'reviewed' => 0];
        foreach ($bundle['curricula'] as $curriculum) {
            foreach ($curriculum->subjects as $subject) {
                $chapters = 0;
                $written = 0;
                $questions = 0;
                $reviewed = 0;
                foreach ($subject->units as $unit) {
                    foreach ($unit->chapters as $chapter) {
                        $chapters++;
                        if ($chapter->file === null) {
                            continue;
                        }
                        $written++;
                        $questions += count($chapter->questions);
                        foreach ($chapter->questions as $q) {
                            if (isset($bundle['reviews'][$q->uid])) {
                                $reviewed++;
                            }
                        }
                        if ($min > 0 && count($chapter->questions) < $min) {
                            $this->warn("{$chapter->code}: only ".count($chapter->questions)." questions (target {$min})");
                        }
                    }
                }
                $rows[] = ["G{$curriculum->grade} {$subject->code}", $chapters, $written, $questions, $reviewed];
                $totals['chapters'] += $chapters;
                $totals['written'] += $written;
                $totals['questions'] += $questions;
                $totals['reviewed'] += $reviewed;
            }
        }
        $rows[] = ['Total', ...array_values($totals)];
        $this->table(['Subject', 'Chapters', 'Written', 'Questions', 'Second-solved'], $rows);
        $this->info('Content is valid.');

        return self::SUCCESS;
    }
}
