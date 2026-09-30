<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Intentos fallidos permitidos por email e IP antes de bloquear un minuto. */
    public const INTENTOS = 5;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'recordar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribí tu email.',
            'email.email' => 'El email no es válido.',
            'password.required' => 'Escribí tu contraseña.',
        ];
    }

    /** Valida las credenciales con límite de intentos. El mensaje de error no dice cuál de los dos datos falló. */
    public function autenticar(): void
    {
        $this->asegurarQueNoEstaBloqueado();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('recordar'))) {
            RateLimiter::hit($this->claveDeIntentos());

            throw ValidationException::withMessages([
                'email' => 'El email o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($this->claveDeIntentos());
    }

    private function asegurarQueNoEstaBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->claveDeIntentos(), self::INTENTOS)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->claveDeIntentos());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos. Probá de nuevo en {$segundos} segundos.",
        ]);
    }

    private function claveDeIntentos(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
