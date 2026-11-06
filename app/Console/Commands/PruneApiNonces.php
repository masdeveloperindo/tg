<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneApiNonces extends Command
{
    protected $signature = 'tg:prune-api-nonces {--apply : Remove expired nonces; default dry run}';

    protected $description = 'Remove only API nonces whose ten-minute replay window has expired';

    public function handle(): int
    {
        $cutoff = now();
        if (! $this->option('apply')) {
            $this->info('Dry run: '.DB::table('api_nonces')->where('expires_at', '<=', $cutoff)->count().' expired nonces.');

            return self::SUCCESS;
        }
        do {
            $count = DB::transaction(function () use ($cutoff) {
                $ids = DB::table('api_nonces')->where('expires_at', '<=', $cutoff)->orderBy('id')->limit(1000)->lockForUpdate()->pluck('id');

                return DB::table('api_nonces')->whereIn('id', $ids)->delete();
            });
        } while ($count > 0);
        $this->info('Expired API nonces removed.');

        return self::SUCCESS;
    }
}
