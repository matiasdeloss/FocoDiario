<?php

namespace App\Http\Requests;

use App\Enums\TipoIntervalo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntervaloEstudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoIntervalo::class)],
            'clave' => ['required', 'string', 'max:60'],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'planificado_seg' => ['nullable', 'integer', 'between:0,10800'],
            'pausado_seg' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'completado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Indicá el tipo de intervalo.',
            'tipo.enum' => 'El tipo de intervalo no es válido.',
            'clave.required' => 'Falta la clave que identifica el intervalo.',
            'clave.max' => 'La clave del intervalo es demasiado larga.',
            'inicio.required' => 'Indicá la hora de inicio.',
            'inicio.date' => 'La hora de inicio no es válida.',
            'fin.required' => 'Indicá la hora de fin.',
            'fin.date' => 'La hora de fin no es válida.',
            'fin.after_or_equal' => 'La hora de fin no puede ser anterior a la de inicio.',
            'planificado_seg.integer' => 'Los segundos planificados deben ser un número entero.',
            'planificado_seg.between' => 'Los segundos planificados deben estar entre 0 y 10800 (3 horas).',
            'pausado_seg.integer' => 'El tiempo en pausa debe ser un número entero de segundos.',
            'pausado_seg.min' => 'El tiempo en pausa no puede ser negativo.',
            'pausado_seg.max' => 'El tiempo en pausa es demasiado largo.',
            'completado.required' => 'Indicá si el intervalo se completó.',
            'completado.boolean' => 'El valor de completado no es válido.',
        ];
    }
}
