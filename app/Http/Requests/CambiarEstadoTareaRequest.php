<?php

namespace App\Http\Requests;

use App\Enums\EstadoTarea;
use App\Models\ColumnaTablero;
use App\Models\Tarea;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoTarea::class)],
        ];
    }

    /** Rechaza un estado que el tablero de la tarea no tiene como columna (solo "En progreso" puede faltar). */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $estado = EstadoTarea::tryFrom((string) $this->input('estado', ''));
            $tarea = $this->route('tarea');

            if ($estado !== null && $tarea instanceof Tarea && ColumnaTablero::faltaCategoria($estado, $tarea->columna?->tablero_id)) {
                $validador->errors()->add('estado', ColumnaTablero::mensajeSinCategoria($estado));
            }
        });
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Indicá el nuevo estado.',
            'estado.enum' => 'El estado elegido no es válido.',
        ];
    }
}
