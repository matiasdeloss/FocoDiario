<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use App\Models\Contexto;
use App\Support\ReglasDeUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear o editar una actividad de la agenda (internamente, un contexto de tipo materia con color). */
class ActividadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Contexto|null $actual */
        $actual = $this->route('actividad');
        $padreId = $actual?->contexto_padre_id;

        return [
            'nombre' => [
                'required', 'string', 'max:80',
                // Mismo criterio que los contextos de Notas: el nombre es único dentro de su padre.
                ReglasDeUsuario::unico('contextos', 'nombre')
                    ->where(fn ($consulta) => $padreId === null
                        ? $consulta->whereNull('contexto_padre_id')
                        : $consulta->where('contexto_padre_id', $padreId))
                    ->ignore($actual?->id),
            ],
            'color' => ['required', Rule::in(ColorActividad::valores())],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribí un nombre para la actividad.',
            'nombre.max' => 'El nombre no puede superar los 80 caracteres.',
            'nombre.unique' => 'Ya existe una actividad (o un contexto de Notas) con ese nombre.',
            'color.required' => 'Elegí un color para la actividad.',
            'color.in' => 'Elegí un color de la paleta.',
        ];
    }
}
