<?php

namespace App\Http\Requests;

use App\Models\Tablero;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Eliminar un tablero: hay que elegir a qué tablero pasan sus tarjetas, y no se puede eliminar el último. */
class EliminarTableroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Tablero $tablero */
        $tablero = $this->route('tablero');

        return [
            'destino_id' => ['required', 'integer', ReglasDeUsuario::existe('tableros')->whereNot('id', $tablero->id)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (Tablero::count() <= 1) {
                    $validator->errors()->add('tablero', 'No se puede eliminar el último tablero.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'destino_id.required' => 'Elegí a qué tablero pasan las tarjetas del tablero que eliminás.',
            'destino_id.exists' => 'El tablero de destino no es válido.',
        ];
    }
}
