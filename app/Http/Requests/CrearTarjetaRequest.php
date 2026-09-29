<?php

namespace App\Http\Requests;

use App\Services\Calendario\TarjetasCalendario;
use Illuminate\Foundation\Http\FormRequest;

class CrearTarjetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:'.implode(',', TarjetasCalendario::TIPOS)],
            'fecha' => ['nullable', 'date_format:'.implode(',', TarjetasCalendario::FORMATOS_FECHA)],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Elegí qué querés crear: tarea, recordatorio o nota.',
            'tipo.in' => 'El tipo elegido no es válido.',
            'fecha.date_format' => 'La fecha no es válida.',
        ];
    }
}
