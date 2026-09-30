<?php

namespace App\Http\Requests;

use App\Models\ColumnaTablero;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Eliminar una columna: sus tareas se reasignan a otra columna. */
class EliminarColumnaTableroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ColumnaTablero $columna */
        $columna = $this->route('columna');

        return [
            'reasignar_a' => [
                $columna->tareas()->exists() ? 'required' : 'nullable',
                'integer',
                ReglasDeUsuario::existe('columnas_tablero')->whereNot('id', $columna->id),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var ColumnaTablero $columna */
                $columna = $this->route('columna');

                if ($columna->categoriaObligatoria()) {
                    $validator->errors()->add('columna', "Es la única columna de tipo \"{$columna->categoria->etiqueta()}\"; el tablero necesita al menos una.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reasignar_a.required' => 'Elegí a qué columna pasan las tareas de la columna que eliminás.',
            'reasignar_a.exists' => 'La columna de destino no es válida.',
        ];
    }
}
