<?php

use App\Enums\UserRole;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\CorrelateRequest;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureAdminSessionFresh;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\PrivateResponseHeaders;
use App\Http\Middleware\ProtectLivewireResponse;
use App\Support\RequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelateRequest::class);
        $middleware->web(append: [ProtectLivewireResponse::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route(
            $request->user()?->role === UserRole::Admin ? 'admin' : 'member',
        ));
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'client' => EnsureClient::class,
            'admin.role' => EnsureAdminRole::class,
            'admin.session' => EnsureAdminSessionFresh::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
            'password.changed' => EnsurePasswordChanged::class,
            'private.response' => PrivateResponseHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'otp', 'recovery_code', 'contact_value', 'account_number', 'license_key']);
        $exceptions->report(function (Throwable $error): void {
            Log::error('application.error', ['request_id' => RequestId::current(), 'status' => 500]);
        })->stop();
        $exceptions->render(function (Throwable $error, Request $request) {
            if ($error instanceof ValidationException || $error instanceof AuthenticationException) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
            $message = match ($status) {
                403 => 'Anda tidak memiliki akses untuk tindakan ini.',
                404 => 'Halaman atau data tidak ditemukan.',
                419 => 'Sesi berakhir. Muat ulang halaman dan masuk kembali.',
                429 => 'Terlalu banyak permintaan. Tunggu sebelum mencoba kembali.',
                default => 'Permintaan belum dapat diselesaikan. Periksa status sebelum mencoba kembali.',
            };
            $id = RequestId::current();
            $headers = $error instanceof HttpExceptionInterface ? array_intersect_key($error->getHeaders(), array_flip(['Retry-After', 'Allow'])) : [];

            return ($request->expectsJson() || $request->is('api/*'))
                ? response()->json(['message' => $message, 'code' => 'HTTP_'.$status, 'request_id' => $id], $status, $headers)
                : response()->view('errors.safe', compact('status', 'message', 'id'), $status, $headers);
        });
        $exceptions->respond(function (Response $response) {
            $response->headers->set('X-Request-ID', RequestId::current());
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
