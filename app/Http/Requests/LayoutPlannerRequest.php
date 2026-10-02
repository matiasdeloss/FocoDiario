<?php

namespace App\Http\Requests;

use App\Models\Caja;
use App\Services\Agenda\PlannerLayout;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Posición y tamaño de las tarjetas del planner semanal (siete días y cajas de la semana), enviados juntos. */
class LayoutPlannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tarjetas' => ['required', 'array', 'min:1', 'max:'.count(PlannerLayout::claves())],
            'tarjetas.*.clave' => ['required', 'string', 'distinct', Rule::in(PlannerLayout::claves())],
            ...collect(PlannerLayout::reglasTarjeta())->mapWithKeys(fn (array $reglas, string $campo) => ["tarjetas.*.$campo" => $reglas])->all(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                foreach ((array) $this->input('tarjetas', []) as $indice => $tarjeta) {
                    if ($validador->errors()->hasAny(["tarjetas.$indice.x", "tarjetas.$indice.ancho"])) {
                        continue;
                    }

                    if (PlannerLayout::seSale((int) ($tarjeta['x'] ?? 0), (int) ($tarjeta['ancho'] ?? 1))) {
                        $validador->errors()->add("tarjetas.$indice.ancho", 'Una tarjeta se sale del planner: la posición y el ancho suman más de '.Caja::COLUMNAS.' columnas.');
                    }
                }
            },
        ];
    }

    /** Las tarjetas validadas, indexadas por clave. */
    public function tarjetas(): array
    {
        return collect($this->validated('tarjetas'))
            ->mapWithKeys(fn (array $t) => [$t['clave'] => [
                'x' => (int) $t['x'],
                'y' => (int) $t['y'],
                'ancho' => (int) $t['ancho'],
                'alto' => (int) $t['alto'],
            ]])
            ->all();
    }

    public function messages(): array
    {
        return [
            'tarjetas.required' => 'Faltan las tarjetas a acomodar.',
            'tarjetas.array' => 'El formato de las tarjetas no es válido.',
            'tarjetas.min' => 'Faltan las tarjetas a acomodar.',
            'tarjetas.max' => 'Son demasiadas tarjetas para acomodar juntas.',
            'tarjetas.*.clave.in' => 'Una de las tarjetas no existe en el planner.',
            'tarjetas.*.clave.distinct' => 'Una tarjeta está repetida.',
            'tarjetas.*.clave.required' => 'Falta indicar a qué tarjeta corresponde una posición.',
            'tarjetas.*.x.min' => 'La posición horizontal no puede ser negativa.',
            'tarjetas.*.x.max' => 'La posición horizontal debe estar entre 0 y '.(Caja::COLUMNAS - 1).'.',
            'tarjetas.*.y.min' => 'La posición vertical no puede ser negativa.',
            'tarjetas.*.y.max' => 'La posición vertical es demasiado grande.',
            'tarjetas.*.ancho.min' => 'El ancho mínimo es de 1 columna.',
            'tarjetas.*.ancho.max' => 'El ancho máximo es de '.Caja::COLUMNAS.' columnas.',
            'tarjetas.*.alto.min' => 'El alto mínimo es de 1 fila.',
            'tarjetas.*.alto.max' => 'El alto máximo es de '.Caja::MAX_ALTO.' filas.',
            'tarjetas.*.x.required' => 'Falta la posición horizontal de una tarjeta.',
            'tarjetas.*.y.required' => 'Falta la posición vertical de una tarjeta.',
            'tarjetas.*.ancho.required' => 'Falta el ancho de una tarjeta.',
            'tarjetas.*.alto.required' => 'Falta el alto de una tarjeta.',
        ];
    }
}
