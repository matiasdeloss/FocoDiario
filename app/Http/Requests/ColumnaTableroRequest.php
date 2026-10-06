<?php

namespace App\Http\Requests;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear (POST) o modificar (PATCH) una columna del tablero. */
class ColumnaTableroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:60'],
            'categoria' => ['sometimes', Rule::enum(EstadoTarea::class)],
            // Al crear: en qué tablero va (por defecto, el principal).
            'tablero_id' => ['sometimes', 'nullable', 'integer', ReglasDeUsuario::existe('tableros')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var ColumnaTablero|null $columna */
                $columna = $this->route('columna');

                if ($columna?->fija) {
                    $validator->errors()->add('columna', 'La columna "Sin asignar" es fija: no se puede renombrar ni cambiar de tipo.');

                    return;
                }

                if ($columna === null || ! $this->has('categoria') || $validator->errors()->has('categoria')) {
                    return;
                }

                if ($this->input('categoria') !== $columna->categoria->value && $columna->categoriaObligatoria()) {
                    $validator->errors()->add('categoria', "Es la única columna de tipo \"{$columna->categoria->etiqueta()}\"; el tablero necesita al menos una.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribí un nombre para la columna.',
            'nombre.max' => 'El nombre no puede superar los 60 caracteres.',
            'categoria.enum' => 'El tipo de columna no es válido.',
            'tablero_id.exists' => 'El tablero elegido no existe.',
        ];
    }
}
