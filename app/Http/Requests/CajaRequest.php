<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasDeContenidoDeCaja;
use App\Models\Caja;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Crear (POST) o modificar (PATCH, campos sueltos) una caja de la hoja de un día. */
class CajaRequest extends FormRequest
{
    use ReglasDeContenidoDeCaja;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $alCrear = $this->isMethod('POST');

        return [
            'fecha' => [$alCrear ? 'required' : 'prohibited', 'date_format:Y-m-d'],
            'contexto_id' => ['sometimes', 'nullable', 'integer', 'exists:contextos,id'],
            'titulo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
            'hora_fin' => ['sometimes', 'nullable', 'date_format:H:i'],
            'x' => ['sometimes', 'integer', 'min:0', 'max:'.(Caja::COLUMNAS - 1)],
            'y' => ['sometimes', 'integer', 'min:0', 'max:'.Caja::MAX_FILA],
            'ancho' => ['sometimes', 'integer', 'min:1', 'max:'.Caja::COLUMNAS],
            'alto' => ['sometimes', 'integer', 'min:1', 'max:'.Caja::MAX_ALTO],
            'hecha' => ['sometimes', 'boolean'],
            ...$this->reglasDeContenido($alCrear),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                $this->validarHoras($validador);
                $this->validarGrilla($validador);
            },
        ];
    }

    /** La hora de fin necesita una de inicio y tiene que ser posterior (se compara con lo ya guardado si no llega). */
    private function validarHoras(Validator $validador): void
    {
        if ($validador->errors()->hasAny(['hora_inicio', 'hora_fin'])) {
            return;
        }

        /** @var Caja|null $actual */
        $actual = $this->route('caja');
        $inicio = $this->has('hora_inicio') ? $this->input('hora_inicio') : $actual?->hora_inicio;
        $fin = $this->has('hora_fin') ? $this->input('hora_fin') : $actual?->hora_fin;

        if ($fin === null) {
            return;
        }

        if ($inicio === null) {
            $validador->errors()->add('hora_inicio', 'Para poner una hora de fin, primero elegí la hora de inicio.');
        } elseif ($fin <= $inicio) {
            $validador->errors()->add('hora_fin', 'La hora de fin tiene que ser posterior a la de inicio.');
        }
    }

    /** La caja no puede salirse de las 12 columnas. */
    private function validarGrilla(Validator $validador): void
    {
        if ($validador->errors()->hasAny(['x', 'ancho'])) {
            return;
        }

        /** @var Caja|null $actual */
        $actual = $this->route('caja');
        $x = $this->has('x') ? (int) $this->input('x') : ($actual?->x ?? 0);
        $ancho = $this->has('ancho') ? (int) $this->input('ancho') : ($actual?->ancho ?? 6);

        if ($x + $ancho > Caja::COLUMNAS) {
            $validador->errors()->add('ancho', 'La caja se sale de la hoja: la posición y el ancho suman más de '.Caja::COLUMNAS.' columnas.');
        }
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Falta la fecha de la hoja.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.prohibited' => 'Una caja no se puede pasar a otro día.',
            'contexto_id.exists' => 'La actividad elegida no existe.',
            'contexto_id.integer' => 'La actividad elegida no es válida.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM.',
            'x.min' => 'La posición horizontal no puede ser negativa.',
            'x.max' => 'La posición horizontal debe estar entre 0 y '.(Caja::COLUMNAS - 1).'.',
            'y.min' => 'La posición vertical no puede ser negativa.',
            'y.max' => 'La posición vertical es demasiado grande.',
            'ancho.min' => 'El ancho mínimo es de 1 columna.',
            'ancho.max' => 'El ancho máximo es de '.Caja::COLUMNAS.' columnas.',
            'alto.min' => 'El alto mínimo es de 1 fila.',
            'alto.max' => 'El alto máximo es de '.Caja::MAX_ALTO.' filas.',
            'hecha.boolean' => 'El estado "hecha" no es válido.',
            ...$this->mensajesDeContenido(),
        ];
    }

    /** Datos listos para guardar (los ítems normalizados). */
    public function datosCaja(): array
    {
        $datos = $this->validated();

        if (array_key_exists('items', $datos)) {
            $datos['items'] = $this->itemsNormalizados($datos['items']);
        }

        return $datos;
    }
}
