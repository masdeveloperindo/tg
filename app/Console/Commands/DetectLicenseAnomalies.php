<?php

namespace App\Console\Commands;

use App\Actions\Operations\DetectAnomalies;
use Illuminate\Console\Command;

class DetectLicenseAnomalies extends Command
{
    protected $signature = 'tg:detect-license-anomalies';

    protected $description = 'Deduplicate license anomaly flags for manual review; never suspend automatically';

    public function handle(DetectAnomalies $detector): int
    {
        $this->info('Created '.$detector->run().' new flags. No licenses suspended.');

        return self::SUCCESS;
    }
}
