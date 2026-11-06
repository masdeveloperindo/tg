<?php

use App\Http\Controllers\Api\V1\LicenseVerifyController;
use App\Http\Middleware\LicenseApiBoundary;
use Illuminate\Support\Facades\Route;

Route::post('/v1/licenses/verify', LicenseVerifyController::class)->middleware(LicenseApiBoundary::class)->name('api.v1.licenses.verify');
// Deactivation is deliberately unavailable: a leaked key cannot revoke a license.

Route::post('/v1/licenses/deactivate', fn () => abort(404))->name('api.v1.licenses.deactivate');
