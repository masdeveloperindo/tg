<?php

namespace App\Http\Controllers;

use App\Actions\Auth\Authentication;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(Authentication $authentication): RedirectResponse
    {
        $authentication->logout();

        return redirect()->route('login');
    }
}
