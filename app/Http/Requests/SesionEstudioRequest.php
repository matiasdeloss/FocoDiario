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
            'foco_min' => ['required', 'integer', 'between:'.implode(',', $limites['foco'])],
            'descanso_min' => ['required', 'integer', 'between:'.implode(',', $limites['descanso'])],
            'descanso_largo_min' => ['required', 'integer', 'between:'.implode(',', $limites['largo'])],
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
            'foco_min.required' => 'Indicá los minutos de foco.',
            'foco_min.integer' => 'Los minutos de foco deben ser un número entero.',
            'foco_min.between' => "El foco debe durar entre {$limites['foco'][0]} y {$limites['foco'][1]} minutos.",
            'descanso_min.required' => 'Indicá los minutos de descanso corto.',
            'descanso_min.integer' => 'Los minutos de descanso corto deben ser un número entero.',
            'descanso_min.between' => "El descanso corto debe durar entre {$limites['descanso'][0]} y {$limites['descanso'][1]} minutos.",
            'descanso_largo_min.required' => 'Indicá los minutos de descanso largo.',
            'descanso_largo_min.integer' => 'Los minutos de descanso largo deben ser un número entero.',
            'descanso_largo_min.between' => "El descanso largo debe durar entre {$limites['largo'][0]} y {$limites['largo'][1]} minutos.",
            'pomodoros_antes_largo.required' => 'Indicá cuántos pomodoros hacer antes del descanso largo.',
            'pomodoros_antes_largo.integer' => 'La cantidad de pomodoros debe ser un número entero.',
            'pomodoros_antes_largo.between' => "Los pomodoros antes del descanso largo deben ser entre {$limites['ciclos'][0]} y {$limites['ciclos'][1]}.",
        ];
    }
}
