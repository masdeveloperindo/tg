<?php

use App\Http\Controllers\LogoutController;
use App\Http\Controllers\ProductAssetController;
use App\Livewire\Auth;
use App\Livewire\Home;
use App\Livewire\Products\Editor;
use App\Livewire\Products\Index;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::middleware(['guest', 'private.response'])->group(function (): void {
    Route::get('/login', Auth\Login::class)->name('login');
    Route::get('/register', Auth\Register::class)->name('register');
});

Route::post('/logout', LogoutController::class)->middleware(['auth', 'private.response'])->name('logout');

Route::middleware(['auth', 'active', 'admin.session', 'auth.session', 'password.changed', 'private.response'])->group(function (): void {
    Route::get('/password/change', Auth\ChangePassword::class)->name('password.change');

    Route::middleware('client')->group(function (): void {
        Route::view('/member', 'auth.member')->name('member');
        Route::get('/member/profil', Auth\ChangePassword::class)->name('member.profile');
    });

    Route::middleware('admin.role')->group(function (): void {
        Route::get('/admin/2fa/setup', Auth\TwoFactorSetup::class)->name('admin.2fa.setup');
        Route::get('/admin/2fa/challenge', Auth\TwoFactorChallenge::class)->name('admin.2fa.challenge');

        Route::middleware('admin.2fa')->group(function (): void {
            Route::get('/admin', function () {
                Gate::authorize('viewAny', User::class);

                return view('auth.admin');
            })->name('admin');
            Route::get('/admin/reauth', Auth\Reauthenticate::class)->name('admin.reauth');
            Route::get('/admin/produk', Index::class)->name('admin.produk');
            Route::get('/admin/produk/create', Editor::class)->name('admin.produk.create');
            Route::get('/admin/produk/{id}/edit', Editor::class)->whereNumber('id')->name('admin.produk.id.edit');
            Route::get('/admin/produk/media/{media}', [ProductAssetController::class, 'image'])->whereNumber('media')->name('admin.produk.media');
            Route::get('/admin/produk/versi/{version}', [ProductAssetController::class, 'version'])->whereNumber('version')->name('admin.produk.version');
            Route::get('/admin/produk/laporan/{performance}', [ProductAssetController::class, 'report'])->whereNumber('performance')->name('admin.produk.report');
            Route::get('/admin/client', Auth\AdminClients::class)->name('admin.clients');
            Route::get('/admin/2fa/recovery', function () {
                $codes = session()->pull('auth.recovery_codes_once');
                abort_unless(is_array($codes) && count($codes) === 10, 404);

                return view('auth.recovery-codes', ['codes' => $codes]);
            })->name('admin.2fa.recovery');
            Route::get('/admin/client/password-result', function () {
                $target = User::findOrFail(session()->pull('auth.temporary_password_user_id'));
                Gate::authorize('resetPassword', $target);
                $password = session()->pull('auth.temporary_password_once');
                abort_unless(is_string($password) && $password !== '', 404);

                return view('auth.temporary-password', ['temporaryPassword' => $password]);
            })->name('admin.clients.password-result');
        });
    });
});
