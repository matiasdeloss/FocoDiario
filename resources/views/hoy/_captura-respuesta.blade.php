{{-- Respuesta HTMX de la captura rápida: el formulario y, fuera de banda, el resumen de errores y las tarjetas de Hoy que cambiaron.
     La confirmación de lo guardado viaja en el encabezado HX-Trigger (toast), no en el cuerpo. --}}
@include('hoy._captura-form', ['recienGuardado' => isset($mensaje)])

<div id="nota-rapida-aviso" hx-swap-oob="innerHTML">
    @if (! isset($mensaje) && $errors->any())
        <div class="hoy-aviso es-error"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Revisá los campos marcados.</div>
    @endif
</div>

@if (! empty($tareas))
    @include('hoy._tareas', $tareas + ['oob' => true])
@endif
@if (! empty($recordatorios))
    @include('hoy._recordatorios', $recordatorios + ['oob' => true])
@endif
@if (! empty($semana))
    @include('hoy._semana', ['semana' => $semana, 'oob' => true])
@endif
