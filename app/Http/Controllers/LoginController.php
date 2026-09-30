<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Entrada a la app. Hay una sola cuenta (se crea con php artisan foco:usuario): no hay registro público. */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->autenticar();

        $request->session()->regenerate();

        return redirect()->intended(route('hoy'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Token CSRF nuevo para las páginas que quedaron abiertas más que la sesión (lo pide resources/js/red.js ante un 419). */
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()]);
    }
}
