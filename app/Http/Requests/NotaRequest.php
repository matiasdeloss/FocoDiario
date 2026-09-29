<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'contenido' => ['required_without:titulo', 'nullable', 'string', 'max:5000'],
            'contexto_id' => ['nullable', 'integer', 'exists:contextos,id'],
            'fecha' => ['nullable', 'date'],
            'fijada' => ['nullable', 'boolean'],
            'origen' => ['nullable', 'in:hoy'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'contenido.required_without' => 'Escribí la nota antes de guardar.',
            'contenido.required' => 'Escribí la nota antes de guardar.',
            'contenido.max' => 'La nota no puede superar los 5000 caracteres.',
            'contexto_id.exists' => 'El destino elegido no existe.',
            'contexto_id.integer' => 'El destino elegido no es válido.',
            'fecha.date' => 'La fecha no es una fecha válida.',
            'fijada.boolean' => 'El valor de "fijada" no es válido.',
        ];
    }

    /** Datos listos para guardar (sin campos auxiliares del formulario). */
    public function datosNota(): array
    {
        $datos = collect($this->validated())->except('origen')->all();

        if (array_key_exists('contenido', $datos) && $datos['contenido'] === null) {
            $datos['contenido'] = '';
        }

        if ($this->has('fijada')) {
            $datos['fijada'] = $this->boolean('fijada');
        }

        return $datos;
    }

    /**
     * La nota rápida de Hoy se envía con HTMX: si falla la validación se devuelve
     * el mismo formulario con sus errores en vez de redirigir.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->header('HX-Request') && $this->input('origen') === 'hoy') {
            throw new HttpResponseException(
                response(
                    view('hoy._nota-rapida', ['valores' => $this->only('contenido', 'contexto_id', 'fecha')])
                        ->withErrors($validator)
                        ->render(),
                ),
            );
        }

        parent::failedValidation($validator);
    }
}
