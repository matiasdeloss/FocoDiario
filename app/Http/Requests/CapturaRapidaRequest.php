<?php

namespace App\Http\Requests;

use App\Enums\ColorNota;
use App\Models\Contexto;
use App\Services\Calendario\TarjetasCalendario;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/** Captura rápida de Hoy: título, descripción y tipo (tarea, recordatorio o nota). */
class CapturaRapidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(TarjetasCalendario::TIPOS)],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            // La hora solo aplica al recordatorio y necesita un día.
            'hora' => ['exclude_unless:tipo,recordatorio', 'nullable', 'date_format:H:i'],
            // El destino y el color solo aplican a la nota.
            'contexto_id' => ['exclude_unless:tipo,nota', 'nullable', 'integer', ReglasDeUsuario::existe('contextos')],
            'color' => ['exclude_unless:tipo,nota', 'nullable', Rule::enum(ColorNota::class)],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            if ($this->input('tipo') === 'recordatorio' && filled($this->input('hora')) && blank($this->input('fecha'))) {
                $validador->errors()->add('fecha', 'Elegí también el día para esa hora.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Elegí si es una tarea, un recordatorio o una nota.',
            'tipo.in' => 'El tipo elegido no es válido.',
            'titulo.required' => 'Escribí un título.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 5000 caracteres.',
            'fecha.date_format' => 'La fecha no es una fecha válida.',
            'hora.date_format' => 'La hora no es válida.',
            'contexto_id.exists' => 'El destino elegido no existe.',
            'contexto_id.integer' => 'El destino elegido no es válido.',
            'color.enum' => 'El color elegido no es válido.',
        ];
    }

    /** Solo los campos que corresponden al tipo elegido. */
    public function datosCaptura(): array
    {
        return $this->validated();
    }

    /** Con HTMX se devuelve el mismo formulario con sus errores (y lo escrito) en vez de redirigir. */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->header('HX-Request')) {
            throw new HttpResponseException(
                response(
                    view('hoy._captura-respuesta', [
                        'valores' => $this->only('tipo', 'titulo', 'descripcion', 'fecha', 'hora', 'contexto_id', 'color'),
                        'destinos' => Contexto::opciones(),
                    ])->withErrors($validator)->render(),
                ),
            );
        }

        parent::failedValidation($validator);
    }
}
