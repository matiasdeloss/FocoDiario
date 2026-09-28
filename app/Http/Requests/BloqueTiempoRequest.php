<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class BloqueTiempoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'inicio' => ['required', 'date_format:H:i'],
            'fin' => ['required', 'date_format:H:i', 'after:inicio'],
            'tarea_id' => ['nullable', 'integer', 'exists:tareas,id'],
            'concentracion' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Indicá el día del bloque.',
            'fecha.date_format' => 'El día no es válido.',
            'categoria_id.required' => 'Elegí una categoría.',
            'categoria_id.exists' => 'La categoría elegida no existe.',
            'inicio.required' => 'Indicá la hora de inicio.',
            'inicio.date_format' => 'La hora de inicio no es válida.',
            'fin.required' => 'Indicá la hora de fin.',
            'fin.date_format' => 'La hora de fin no es válida.',
            'fin.after' => 'La hora de fin tiene que ser posterior a la de inicio.',
            'tarea_id.exists' => 'La tarea elegida ya no existe.',
            'concentracion.between' => 'La concentración va de 1 a 5.',
            'concentracion.integer' => 'La concentración va de 1 a 5.',
        ];
    }

    /** Datos listos para guardar: inicio y fin combinados con la fecha. */
    public function datosBloque(): array
    {
        $datos = $this->validated();
        $fecha = CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        return [
            'categoria_id' => $datos['categoria_id'],
            'tarea_id' => $datos['tarea_id'] ?? null,
            'inicio' => $fecha->setTimeFromTimeString($datos['inicio']),
            'fin' => $fecha->setTimeFromTimeString($datos['fin']),
            'concentracion' => $datos['concentracion'] ?? null,
        ];
    }
}
