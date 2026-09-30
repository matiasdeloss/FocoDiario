<?php

namespace App\Http\Requests;

use App\Models\Caja;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Posición y tamaño de varias cajas de una hoja, enviados juntos cuando se mueve o redimensiona una. */
class LayoutCajasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fecha = (string) $this->route('fecha');

        return [
            'cajas' => ['required', 'array', 'min:1', 'max:200'],
            'cajas.*.id' => [
                'required', 'integer', 'distinct',
                // Solo se aceptan cajas de esta hoja.
                ReglasDeUsuario::existe('cajas')->where(fn ($consulta) => $consulta->whereDate('fecha', $fecha)),
            ],
            'cajas.*.x' => ['required', 'integer', 'min:0', 'max:'.(Caja::COLUMNAS - 1)],
            'cajas.*.y' => ['required', 'integer', 'min:0', 'max:'.Caja::MAX_FILA],
            'cajas.*.ancho' => ['required', 'integer', 'min:1', 'max:'.Caja::COLUMNAS],
            'cajas.*.alto' => ['required', 'integer', 'min:1', 'max:'.Caja::MAX_ALTO],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                foreach ((array) $this->input('cajas', []) as $indice => $caja) {
                    if ($validador->errors()->hasAny(["cajas.$indice.x", "cajas.$indice.ancho"])) {
                        continue;
                    }

                    if ((int) ($caja['x'] ?? 0) + (int) ($caja['ancho'] ?? 1) > Caja::COLUMNAS) {
                        $validador->errors()->add("cajas.$indice.ancho", 'Una caja se sale de la hoja: la posición y el ancho suman más de '.Caja::COLUMNAS.' columnas.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cajas.required' => 'Faltan las cajas a acomodar.',
            'cajas.array' => 'El formato de las cajas no es válido.',
            'cajas.min' => 'Faltan las cajas a acomodar.',
            'cajas.max' => 'Son demasiadas cajas para acomodar juntas.',
            'cajas.*.id.exists' => 'Una de las cajas no pertenece a esta hoja.',
            'cajas.*.id.distinct' => 'Una caja está repetida.',
            'cajas.*.x.min' => 'La posición horizontal no puede ser negativa.',
            'cajas.*.x.max' => 'La posición horizontal debe estar entre 0 y '.(Caja::COLUMNAS - 1).'.',
            'cajas.*.y.min' => 'La posición vertical no puede ser negativa.',
            'cajas.*.y.max' => 'La posición vertical es demasiado grande.',
            'cajas.*.ancho.min' => 'El ancho mínimo es de 1 columna.',
            'cajas.*.ancho.max' => 'El ancho máximo es de '.Caja::COLUMNAS.' columnas.',
            'cajas.*.alto.min' => 'El alto mínimo es de 1 fila.',
            'cajas.*.alto.max' => 'El alto máximo es de '.Caja::MAX_ALTO.' filas.',
            'cajas.*.x.required' => 'Falta la posición horizontal de una caja.',
            'cajas.*.y.required' => 'Falta la posición vertical de una caja.',
            'cajas.*.ancho.required' => 'Falta el ancho de una caja.',
            'cajas.*.alto.required' => 'Falta el alto de una caja.',
        ];
    }
}
