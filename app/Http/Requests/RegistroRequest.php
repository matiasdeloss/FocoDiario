<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Crear cuenta: convierte al invitado de la sesión en una cuenta con email y contraseña. */
class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', 'max:255', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.max' => 'El nombre puede tener hasta 80 caracteres.',
            'email.required' => 'Escribí tu email.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'Ya existe una cuenta con ese email. Probá iniciar sesión.',
            'password.required' => 'Elegí una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ];
    }
}
