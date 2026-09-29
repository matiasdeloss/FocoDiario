<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasDeContenidoDeCaja;
use Illuminate\Foundation\Http\FormRequest;

/** Contenido de las cajas Notas y Pendiente de una semana del planner. */
class CajaSemanaRequest extends FormRequest
{
    use ReglasDeContenidoDeCaja;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasDeContenido(true);
    }

    public function messages(): array
    {
        return $this->mensajesDeContenido();
    }

    public function datosCaja(): array
    {
        $datos = $this->validated();

        if (array_key_exists('items', $datos)) {
            $datos['items'] = $this->itemsNormalizados($datos['items']);
        }

        return $datos;
    }
}
