<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Mover una caja del planner desde el calendario: nuevo día y, si corresponde, nueva franja horaria. */
class MoverCajaPlannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La caja se resuelve con el alcance del usuario: una ajena responde 404 antes de llegar acá.
        return true;
    }

    /** "hora_inicio" debe venir siempre; null deja la caja de todo el día (y sin hora de fin). */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['present', 'nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($validador->errors()->hasAny(['hora_inicio', 'hora_fin'])) {
                    return;
                }

                if ($this->input('hora_fin') !== null && $this->input('hora_fin') <= $this->input('hora_inicio')) {
                    $validador->errors()->add('hora_fin', 'La hora de fin tiene que ser posterior a la de inicio.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Indicá el día.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.present' => 'Indicá la hora de inicio, o null para dejarla de todo el día.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM.',
        ];
    }
}
