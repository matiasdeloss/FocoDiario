<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarTarjetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Solo se guardan los campos enviados. */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comentario' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'titulo.string' => 'El título no es válido.',
            'comentario.max' => 'El comentario no puede superar los 5000 caracteres.',
            'comentario.string' => 'El comentario no es válido.',
        ];
    }
}
