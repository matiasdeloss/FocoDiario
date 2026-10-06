<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'q' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', Rule::enum(ColorActividad::class)],
            'fijadas' => ['nullable', 'boolean'],
            'ocultas' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'contexto.regex' => 'El filtro de contexto no es válido.',
        ];
    }
}
