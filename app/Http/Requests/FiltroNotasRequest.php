<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FiltroNotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // "bandeja" = notas sin destino; un número = id de contexto
            'contexto' => ['nullable', 'string', 'regex:/^(bandeja|\d+)$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'contexto.regex' => 'El filtro de contexto no es válido.',
        ];
    }
}
