<?php

namespace App\Http\Requests;

use App\Support\ReglasDeUsuario;
use Illuminate\Foundation\Http\FormRequest;

/** Crear o renombrar un tablero. */
class TableroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60', ReglasDeUsuario::unico('tableros', 'nombre')->ignore($this->route('tablero')?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribí un nombre para el tablero.',
            'nombre.max' => 'El nombre no puede superar los 60 caracteres.',
            'nombre.unique' => 'Ya tenés un tablero con ese nombre.',
        ];
    }
}
