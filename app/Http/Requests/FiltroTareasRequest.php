<?php

namespace App\Http\Requests;

use App\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros de la lista unificada de Tareas y Recordatorios (todos por query string). */
class FiltroTareasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Enlaces viejos: los estados de tarea de antes se traducen a los dos de ahora (abiertas / hechas). */
    protected function prepareForValidation(): void
    {
        $estado = $this->query('estado');

        if ($estado === 'completada') {
            $this->merge(['estado' => 'hechas']);
        } elseif (in_array($estado, ['pendiente', 'en_progreso'], true)) {
            $this->merge(['estado' => 'abiertas']);
        }
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', Rule::in(['todo', 'tarea', 'recordatorio'])],
            'estado' => ['nullable', Rule::in(['abiertas', 'hechas'])],
            'prioridad' => ['nullable', Rule::enum(PrioridadTarea::class)],
            'contexto' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'vista' => ['nullable', 'in:lista,tablero'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo del filtro no es válido.',
            'estado.in' => 'El estado del filtro no es válido.',
            'prioridad.enum' => 'La prioridad del filtro no es válida.',
            'vista.in' => 'La vista elegida no es válida.',
            'contexto.integer' => 'El contexto del filtro no es válido.',
            'q.max' => 'La búsqueda es demasiado larga.',
        ];
    }
}
