<?php

namespace App\Providers;

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureAdminSessionFresh;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\PrivateResponseHeaders;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([
            Authenticate::class,
            AuthenticateSession::class,
            RedirectIfAuthenticated::class,
            EnsureActiveUser::class,
            EnsureClient::class,
            EnsureAdminRole::class,
            EnsureAdminSessionFresh::class,
            EnsureAdminTwoFactor::class,
            EnsurePasswordChanged::class,
            PrivateResponseHeaders::class,
        ]);
    }
}
