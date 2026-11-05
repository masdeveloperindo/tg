<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

class ProtectLivewireResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Livewire::isLivewireRequest()) {
            return app(PrivateResponseHeaders::class)->handle($request, $next);
        }

        return $next($request);
    }
}
