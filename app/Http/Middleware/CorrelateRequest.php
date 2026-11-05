<?php

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CorrelateRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never accept correlation metadata supplied by an untrusted client.
        $request->attributes->remove('request_id');
        $id = RequestId::current();
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $id);

            return $response;
        } catch (Throwable $error) {
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
            throw $error;
        } finally {
            Log::info($status < 400 ? 'request.completed' : 'request.failed', [
                'request_id' => $id, 'actor_id' => $request->user()?->getAuthIdentifier(), 'status' => $status,
            ]);
        }
    }
}
