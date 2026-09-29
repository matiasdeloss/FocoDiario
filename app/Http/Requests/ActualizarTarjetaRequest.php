<?php

namespace App\Http\Requests;

use App\Enums\ColorNota;
use App\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarTarjetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Solo se guardan los campos enviados. */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comentario' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // Solo aplican a su tipo: prioridad y completada a tareas, color a notas.
            'prioridad' => ['sometimes', Rule::enum(PrioridadTarea::class)],
            'completada' => ['sometimes', 'boolean'],
            'color' => ['sometimes', Rule::enum(ColorNota::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'titulo.string' => 'El título no es válido.',
            'comentario.max' => 'El comentario no puede superar los 5000 caracteres.',
            'comentario.string' => 'El comentario no es válido.',
            'prioridad.enum' => 'La prioridad no es válida.',
            'completada.boolean' => 'El estado de la tarea no es válido.',
            'color.enum' => 'El color no es válido.',
        ];
    }
}
