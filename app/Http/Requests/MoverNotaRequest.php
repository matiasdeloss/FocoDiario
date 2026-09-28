<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoverNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contexto_id' => ['nullable', 'integer', 'exists:contextos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'contexto_id.exists' => 'El destino elegido no existe.',
            'contexto_id.integer' => 'El destino elegido no es válido.',
        ];
    }
}
