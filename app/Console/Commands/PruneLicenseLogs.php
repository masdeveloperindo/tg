<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneLicenseLogs extends Command
{
    protected $signature = 'tg:prune-license-logs {--apply : Apply retention; default is dry run}';

    protected $description = 'Expire license telemetry after 90 days and encrypted raw IP after 30 days; preserve all activity audits';

    public function handle(): int
    {
        $expired = now()->subDays(90);
        $ipExpired = now()->subDays(30);
        if (! $this->option('apply')) {
            $this->info('Dry run: '.DB::table('license_logs')->where('created_at', '<', $expired)->count().' telemetry rows; '.DB::table('license_logs')->where('created_at', '<', $ipExpired)->whereNotNull('ip_enc')->count().' encrypted IP values. Activity audit preserved.');

            return self::SUCCESS;
        }
        do {
            $count = DB::transaction(function () use ($expired): int {
                $ids = DB::table('license_logs')->where('created_at', '<', $expired)->orderBy('id')->limit(1000)->lockForUpdate()->pluck('id');

                return DB::table('license_logs')->whereIn('id', $ids)->delete();
            });
        } while ($count > 0);
        do {
            $count = DB::transaction(function () use ($ipExpired): int {
                $ids = DB::table('license_logs')->where('created_at', '<', $ipExpired)->whereNotNull('ip_enc')->orderBy('id')->limit(1000)->lockForUpdate()->pluck('id');

                return DB::table('license_logs')->whereIn('id', $ids)->update(['ip_enc' => null]);
            });
        } while ($count > 0);
        $this->info('Retention applied. Activity audit preserved.');

        return self::SUCCESS;
    }
}
