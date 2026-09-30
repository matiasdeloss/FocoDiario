<?php

namespace App\Http\Requests;

use App\Support\ReglasDeUsuario;
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
            'contexto_id' => ['nullable', 'integer', ReglasDeUsuario::existe('contextos')],
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
