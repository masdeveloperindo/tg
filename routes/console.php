<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tg:expire-orders')->everyMinute()->name('expire-unpaid-orders')->withoutOverlapping(5)->onOneServer();
Schedule::command('tg:detect-license-anomalies')->everyTenMinutes()->name('license-anomalies')->withoutOverlapping(10)->onOneServer();
Schedule::command('tg:prune-api-nonces --apply')->everyTenMinutes()->name('prune-api-nonces')->withoutOverlapping(10)->onOneServer();
Schedule::command('tg:prune-license-logs --apply')->dailyAt('02:00')->timezone('Asia/Jakarta')->name('prune-license-telemetry')->withoutOverlapping(60)->onOneServer();
Schedule::command('tg:reconcile-rebates')->dailyAt('02:30')->timezone('Asia/Jakarta')->name('reconcile-rebates')->withoutOverlapping(60)->onOneServer();
