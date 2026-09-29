<?php

namespace App\Http\Requests;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'proyecto' => ['nullable', 'string', 'max:255'],
            'fecha_limite' => ['nullable', 'date'],
            'prioridad' => ['required', Rule::enum(PrioridadTarea::class)],
            'estado' => ['required', Rule::enum(EstadoTarea::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escribí un título para la tarea.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'descripcion.max' => 'El comentario no puede superar los 5000 caracteres.',
            'proyecto.max' => 'El proyecto no puede superar los 255 caracteres.',
            'fecha_limite.date' => 'La fecha límite no es una fecha válida.',
            'prioridad.required' => 'Elegí una prioridad.',
            'prioridad.enum' => 'La prioridad elegida no es válida.',
            'estado.required' => 'Elegí un estado.',
            'estado.enum' => 'El estado elegido no es válido.',
        ];
    }
}
