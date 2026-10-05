<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Support\ReglasDeUsuario;
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
            // Solo aplican a su tipo (lo demás se ignora): tareas, notas y recordatorios.
            'prioridad' => ['sometimes', Rule::enum(PrioridadTarea::class)],
            'completada' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', Rule::enum(EstadoTarea::class)],
            'color' => ['sometimes', 'nullable', Rule::enum(ColorActividad::class)],
            // Materia de la nota o contexto de la tarea.
            'contexto_id' => ['sometimes', 'nullable', 'integer', ReglasDeUsuario::existe('contextos')],
            'fijada' => ['sometimes', 'boolean'],
            'tarea_id' => ['sometimes', 'nullable', 'integer', ReglasDeUsuario::existe('tareas')],
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
            'estado.enum' => 'El estado elegido no es válido.',
            'contexto_id.exists' => 'El contexto elegido ya no existe.',
            'fijada.boolean' => 'El valor de "fijada" no es válido.',
            'tarea_id.exists' => 'La tarea elegida ya no existe.',
        ];
    }
}
