<?php

namespace App\Console\Commands;

use App\Services\Quality\QualityGate;
use Illuminate\Console\Command;

class QualityPromote extends Command
{
    protected $signature = 'quality:promote';

    protected $description = 'Move well-answered Beta questions to Published; send suspicious ones to review';

    public function handle(QualityGate $gate): int
    {
        $result = $gate->promote();
        $this->info("Published: {$result['published']} · sent to review: {$result['sent_to_review']}");

        return self::SUCCESS;
    }
}
