<?php

namespace App\Console\Commands;

use App\Content\ContentFolder;
use App\Content\ContentImporter;
use App\Content\ContentInvalid;
use Illuminate\Console\Command;

class ContentImport extends Command
{
    protected $signature = 'content:import {path? : content folder (default: CONTENT_PATH)}';

    protected $description = 'Check and import the content files into the database (safe to run again)';

    public function handle(ContentImporter $importer): int
    {
        try {
            $bundle = ContentFolder::validator()->load(ContentFolder::path($this->argument('path')));
        } catch (ContentInvalid $e) {
            foreach ($e->errors as $error) {
                $this->error($error);
            }
            $this->error('Nothing was imported.');

            return self::FAILURE;
        }

        $report = $importer->import($bundle['curricula'], $bundle['reviews']);

        ksort($report->counts);
        $this->table(['What', 'Count'], array_map(fn ($k, $v) => [$k, $v], array_keys($report->counts), $report->counts));
        if ($report->answersRescored > 0) {
            $this->info("Re-scored {$report->answersRescored} student answer(s) after key fixes.");
        }
        foreach ($report->problems as $problem) {
            $this->warn($problem);
        }
        $this->info('Import finished.');

        return self::SUCCESS;
    }
}
