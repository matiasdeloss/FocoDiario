<?php

namespace App\Http\Requests;

use App\Support\ReglasDeUsuario;
use Illuminate\Foundation\Http\FormRequest;

class FiltroEstudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'contexto_id' => ['nullable', 'integer', ReglasDeUsuario::existe('contextos')],
        ];
    }

    public function messages(): array
    {
        return [
            'desde.date_format' => 'La fecha "desde" no es válida.',
            'hasta.date_format' => 'La fecha "hasta" no es válida.',
            'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".',
            'contexto_id.integer' => 'La materia o tema elegido no es válido.',
            'contexto_id.exists' => 'La materia o tema elegido no existe.',
        ];
    }
}
