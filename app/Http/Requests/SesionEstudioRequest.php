<?php

namespace App\Http\Requests;

use App\Enums\EstiloEstudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SesionEstudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limites = config('estudio.limites');

        return [
            'tarea_id' => ['nullable', 'integer', Rule::exists('tareas', 'id')],
            'contexto_id' => ['nullable', 'integer', Rule::exists('contextos', 'id')],
            'tema' => ['nullable', 'string', 'max:255'],
            'estilo' => ['required', Rule::enum(EstiloEstudio::class)],
            'foco_seg' => ['required', 'integer', 'between:'.implode(',', $limites['foco'])],
            'descanso_seg' => ['required', 'integer', 'between:'.implode(',', $limites['descanso'])],
            'descanso_largo_seg' => ['required', 'integer', 'between:'.implode(',', $limites['largo'])],
            'pomodoros_antes_largo' => ['required', 'integer', 'between:'.implode(',', $limites['ciclos'])],
        ];
    }

    public function messages(): array
    {
        $limites = config('estudio.limites');

        return [
            'tarea_id.exists' => 'La tarea elegida ya no existe.',
            'contexto_id.exists' => 'La materia o tema elegido ya no existe.',
            'tema.max' => 'Lo que vas a trabajar no puede superar los 255 caracteres.',
            'estilo.required' => 'Elegí un estilo de estudio.',
            'estilo.enum' => 'El estilo de estudio elegido no es válido.',
            'foco_seg.required' => 'Indicá la duración del foco.',
            'foco_seg.integer' => 'La duración del foco debe ser un número entero de segundos.',
            'foco_seg.between' => "El foco debe durar entre {$this->rango('foco')}.",
            'descanso_seg.required' => 'Indicá la duración del descanso corto.',
            'descanso_seg.integer' => 'La duración del descanso corto debe ser un número entero de segundos.',
            'descanso_seg.between' => "El descanso corto debe durar entre {$this->rango('descanso')}.",
            'descanso_largo_seg.required' => 'Indicá la duración del descanso largo.',
            'descanso_largo_seg.integer' => 'La duración del descanso largo debe ser un número entero de segundos.',
            'descanso_largo_seg.between' => "El descanso largo debe durar entre {$this->rango('largo')}.",
            'pomodoros_antes_largo.required' => 'Indicá cuántos pomodoros hacer antes del descanso largo.',
            'pomodoros_antes_largo.integer' => 'La cantidad de pomodoros debe ser un número entero.',
            'pomodoros_antes_largo.between' => "Los pomodoros antes del descanso largo deben ser entre {$limites['ciclos'][0]} y {$limites['ciclos'][1]}.",
        ];
    }

    /** Rango de una duración en texto: "00:05 y 180:00 (minutos:segundos)". */
    private function rango(string $clave): string
    {
        [$minimo, $maximo] = config('estudio.limites')[$clave];
        $texto = fn (int $seg) => sprintf('%02d:%02d', intdiv($seg, 60), $seg % 60);

        return $texto($minimo).' y '.$texto($maximo).' (min:seg)';
    }
}
