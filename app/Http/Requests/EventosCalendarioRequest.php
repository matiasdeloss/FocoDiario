<?php

namespace App\Http\Requests;

use App\Services\Calendario\EventosCalendario;
use Illuminate\Foundation\Http\FormRequest;

class EventosCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'tipos' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'start.required' => 'Falta el inicio del rango.',
            'start.date' => 'El inicio del rango no es una fecha válida.',
            'end.required' => 'Falta el fin del rango.',
            'end.date' => 'El fin del rango no es una fecha válida.',
            'end.after' => 'El fin del rango debe ser posterior al inicio.',
            'tipos.string' => 'Los tipos deben ir separados por comas.',
        ];
    }

    /** Capas pedidas; sin parámetro, todas. Ignora los tipos desconocidos. */
    public function tipos(): array
    {
        $pedidos = $this->validated('tipos');

        if ($pedidos === null) {
            return EventosCalendario::TIPOS;
        }

        return array_values(array_intersect(EventosCalendario::TIPOS, explode(',', $pedidos)));
    }
}
