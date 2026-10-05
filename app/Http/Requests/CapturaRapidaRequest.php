<?php

namespace App\Http\Requests;

use App\Enums\ColorActividad;
use App\Enums\PrioridadTarea;
use App\Models\Contexto;
use App\Services\Calendario\TarjetasCalendario;
use App\Support\ReglasDeUsuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Captura rápida de Hoy: título, descripción y tipo (tarea, recordatorio o nota).
 * Con tipo tarea acepta también los campos del modal de tareas (contexto, prioridad, columna y color), con las reglas de TareaRequest.
 */
class CapturaRapidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Campos de TareaRequest que la captura reutiliza con sus mismas reglas. */
    private const CAMPOS_DE_TAREA = ['columna_id'];

    public function rules(): array
    {
        $deTarea = collect(Arr::only((new TareaRequest)->rules(), self::CAMPOS_DE_TAREA))
            ->map(fn (array $reglas) => ['exclude_unless:tipo,tarea', ...$reglas])
            ->all();

        return [
            ...$deTarea,
            // El contexto de la tarea viaja aparte del destino de la nota (los dos se llaman contexto_id en sus modelos).
            'tarea_contexto_id' => ['exclude_unless:tipo,tarea', ...(new TareaRequest)->rules()['contexto_id']],
            // Vacía = la de siempre (media); en el modal es obligatoria porque ahí siempre trae un valor.
            'prioridad' => ['exclude_unless:tipo,tarea', 'nullable', Rule::enum(PrioridadTarea::class)],
            'tipo' => ['required', Rule::in(TarjetasCalendario::TIPOS)],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            // La hora solo aplica al recordatorio y necesita un día.
            'hora' => ['exclude_unless:tipo,recordatorio', 'nullable', 'date_format:H:i'],
            // El destino solo aplica a la nota; el color, a la nota y a la tarea.
            'contexto_id' => ['exclude_unless:tipo,nota', 'nullable', 'integer', ReglasDeUsuario::existe('contextos')],
            'color' => ['exclude_unless:tipo,nota,tarea', 'nullable', Rule::enum(ColorActividad::class)],
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
            ...Arr::only((new TareaRequest)->messages(), ['prioridad.enum', 'columna_id.exists', 'columna_id.integer']),
            'tarea_contexto_id.exists' => 'El contexto elegido no existe.',
            'tarea_contexto_id.integer' => 'El contexto elegido no es válido.',
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
                        'valores' => $this->only('tipo', 'titulo', 'descripcion', 'fecha', 'hora', 'contexto_id', 'color', 'tarea_contexto_id', 'prioridad', 'columna_id'),
                        'destinos' => Contexto::opciones(),
                    ])->withErrors($validator)->render(),
                ),
            );
        }

        parent::failedValidation($validator);
    }
}
