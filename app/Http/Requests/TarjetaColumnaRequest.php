<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** "+ Añadir tarjeta" al pie de una columna: solo el título; el resto toma los valores por defecto. */
class TarjetaColumnaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('titulo'))) {
            $this->merge(['titulo' => trim($this->input('titulo'))]);
        }
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escribí un título para la tarjeta.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
        ];
    }
}
