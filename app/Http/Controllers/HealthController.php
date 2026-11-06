<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HealthController
{
    public function __invoke(Request $request)
    {
        $configured = config('tradergaruda.health_token');
        $supplied = $request->bearerToken();
        abort_unless(is_string($configured) && strlen($configured) >= 32 && is_string($supplied) && hash_equals($configured, $supplied), 404);
        $key = 'health:'.Str::uuid();
        $ok = false;
        try {
            DB::select('SELECT 1');
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
        } catch (\Throwable) {
        } finally {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
            }
        }

return response()->json(['status' => $ok ? 'ok' : 'unavailable'], $ok ? 200 : 503)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
