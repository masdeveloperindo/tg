<?php

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LicenseApiBoundary
{
    public function handle(Request $request, Closure $next)
    {
        $error = fn (string $code, int $status, array $headers = []) => response()->json(['code' => $code, 'request_id' => RequestId::current()], $status, ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow', ...$headers]);
        if (! $request->isSecure() && ! app()->environment('testing', 'e2e')) {
            return $error('https_required', 400);
        }
        if (strlen($request->getContent()) > 4096 || (int) $request->header('Content-Length', 0) > 4096) {
            return $error('request_too_large', 413);
        }
        $ip = 'license-api:ip:'.hash_hmac('sha256', (string) $request->ip(), config('app.key'));
        if (RateLimiter::hit($ip, 3600) > 120) {
            return $error('rate_limited', 429, ['Retry-After' => (string) RateLimiter::availableIn($ip)]);
        }
        if (! $request->isJson()) {
            return $error('invalid_request', 422);
        }
        $key = $request->input('license_key');
        if (is_string($key) && strlen($key) <= 100) {
            $bucket = 'license-api:key:'.hash_hmac('sha256', strtoupper(trim($key)), config('app.key'));
            if (RateLimiter::hit($bucket, 3600) > 60) {
                return $error('rate_limited', 429, ['Retry-After' => (string) RateLimiter::availableIn($bucket)]);
            }
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
