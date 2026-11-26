<?php

namespace App\Console\Commands;

use App\Content\ContentFolder;
use App\Content\ContentInvalid;
use App\Content\TextbookReviewImporter;
use Illuminate\Console\Command;

class ContentImportTextbook extends Command
{
    protected $signature = 'content:import-textbook {path? : textbook folder (default: <content folder>/textbook)}';

    protected $description = 'Check and import the solved textbook review exercises (safe to run again)';

    public function handle(): int
    {
        $path = $this->argument('path');
        $folder = is_string($path) && $path !== '' ? rtrim($path, '/') : ContentFolder::path().'/textbook';
        $importer = TextbookReviewImporter::forKnownSubjects();

        try {
            $report = $importer->import($importer->load($folder));
        } catch (ContentInvalid $e) {
            foreach ($e->errors as $error) {
                $this->error($error);
            }
            $this->error('Nothing was imported.');

            return self::FAILURE;
        }

        ksort($report->counts);
        $this->table(['What', 'Count'], array_map(fn ($k, $v) => [$k, $v], array_keys($report->counts), $report->counts));
        $this->info('Import finished.');

        return self::SUCCESS;
    }
}
