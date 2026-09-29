<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use App\Enums\TipoContexto;
use App\Models\Contexto;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContextoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Contexto|null $actual */
        $actual = $this->route('contexto');
        $padreId = $this->input('contexto_padre_id');

        return [
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('contextos', 'nombre')
                    ->where(fn ($consulta) => $padreId === null || $padreId === ''
                        ? $consulta->whereNull('contexto_padre_id')
                        : $consulta->where('contexto_padre_id', $padreId))
                    ->ignore($actual?->id),
            ],
            'tipo' => ['required', Rule::enum(TipoContexto::class)],
            'contexto_padre_id' => [
                'nullable', 'integer', 'exists:contextos,id',
                function (string $atributo, mixed $valor, Closure $fallar) use ($actual) {
                    if ($actual === null || $valor === null) {
                        return;
                    }

                    if ((int) $valor === $actual->id) {
                        $fallar('Un contexto no puede ser su propio padre.');
                    } elseif (in_array((int) $valor, $actual->idsDescendientes(), true)) {
                        $fallar('El padre elegido es un subcontexto de este contexto: se crearía un ciclo.');
                    }
                },
            ],
            'color' => ['nullable', Rule::in(ColorActividad::valores())],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribí un nombre para el contexto.',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un contexto con ese nombre dentro del mismo padre.',
            'tipo.required' => 'Elegí un tipo.',
            'tipo.enum' => 'El tipo elegido no es válido.',
            'contexto_padre_id.exists' => 'El padre elegido no existe.',
            'contexto_padre_id.integer' => 'El padre elegido no es válido.',
            'color.in' => 'Elegí un color de la paleta.',
        ];
    }

    /** Datos listos para guardar: sin color si el formulario no lo envía (casilla "Sin color"). */
    public function datosContexto(): array
    {
        return $this->validated() + ['color' => null];
    }
}
