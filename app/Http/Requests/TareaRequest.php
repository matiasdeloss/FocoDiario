<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Support\ReglasDeUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El alta rápida de Hoy (JSON) solo manda el título: prioridad media y estado pendiente por defecto.
     * El formulario completo sigue exigiendo ambos.
     */
    protected function prepareForValidation(): void
    {
        // Un estado vacío es "sin estado": así no pisa al que fija la columna elegida.
        if ($this->has('estado') && $this->input('estado') === null) {
            $this->request->remove('estado');
        }

        if ($this->expectsJson() && $this->isMethod('POST') && ! $this->filled('columna_id')) {
            $this->mergeIfMissing([
                'prioridad' => PrioridadTarea::Media->value,
                'estado' => EstadoTarea::Pendiente->value,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            // Contexto de la tarea (entorno, materia, tema o proyecto) del propio usuario.
            'contexto_id' => ['nullable', 'integer', ReglasDeUsuario::existe('contextos')],
            'fecha_limite' => ['nullable', 'date_format:Y-m-d'],
            'prioridad' => ['required', Rule::enum(PrioridadTarea::class)],
            // El modal manda la columna del tablero (su tipo fija el estado); el formulario clásico manda el estado.
            'columna_id' => ['nullable', 'integer', ReglasDeUsuario::existe('columnas_tablero')],
            'estado' => ['required_without:columna_id', 'nullable', Rule::enum(EstadoTarea::class)],
            // Color de la tarjeta en el tablero; vacío = sin color (crema).
            'color' => ['nullable', Rule::enum(ColorActividad::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escribí un título para la tarea.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'descripcion.max' => 'El comentario no puede superar los 5000 caracteres.',
            'contexto_id.exists' => 'El contexto elegido no existe.',
            'contexto_id.integer' => 'El contexto elegido no es válido.',
            'fecha_limite.date_format' => 'La fecha límite no es una fecha válida.',
            'prioridad.required' => 'Elegí una prioridad.',
            'prioridad.enum' => 'La prioridad elegida no es válida.',
            'estado.required_without' => 'Elegí un estado.',
            'columna_id.exists' => 'La columna elegida no existe.',
            'columna_id.integer' => 'La columna elegida no es válida.',
            'estado.enum' => 'El estado elegido no es válido.',
            'color.enum' => 'El color elegido no es válido.',
        ];
    }
}
