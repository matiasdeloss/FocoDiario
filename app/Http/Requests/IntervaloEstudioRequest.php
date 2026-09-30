<?php

namespace App\Http\Requests;

use App\Enums\TipoIntervalo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IntervaloEstudioRequest extends FormRequest
{
    /** Fechas ISO 8601 como las de Date.toISOString() del navegador (con o sin milisegundos). */
    private const FORMATO_ISO = 'date_format:Y-m-d\TH:i:sp,Y-m-d\TH:i:s.vp';

    /** Un intervalo (incluido el tiempo libre y sus pausas) no puede abarcar más de un día. */
    public const MAXIMO_SEG = 86400;

    /** Margen para relojes un poco adelantados. */
    private const MARGEN_FUTURO_SEG = 300;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoIntervalo::class)],
            'clave' => ['required', 'string', 'max:60'],
            'inicio' => ['required', self::FORMATO_ISO],
            'fin' => ['required', self::FORMATO_ISO, 'after_or_equal:inicio'],
            'planificado_seg' => ['nullable', 'integer', 'between:0,10800'],
            'pausado_seg' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'completado' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($validador->errors()->hasAny(['inicio', 'fin'])) {
                    return;
                }

                $inicio = CarbonImmutable::parse($this->input('inicio'));
                $fin = CarbonImmutable::parse($this->input('fin'));

                if ($inicio->diffInSeconds($fin, true) > self::MAXIMO_SEG) {
                    $validador->errors()->add('fin', 'Un intervalo no puede durar más de un día.');
                } elseif ($fin->isAfter(now()->addSeconds(self::MARGEN_FUTURO_SEG))) {
                    $validador->errors()->add('fin', 'La hora de fin no puede estar en el futuro.');
                }
            },
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
            'inicio.date_format' => 'La hora de inicio no es válida.',
            'fin.required' => 'Indicá la hora de fin.',
            'fin.date_format' => 'La hora de fin no es válida.',
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
