{{-- Campo propio de una tarea (proyecto, prioridad o columna), compartido por el modal de tareas y la nota rápida de Hoy.
     Requiere: $campo ('proyecto' | 'prioridad' | 'columna'), $prefijo (prefijo de los ids: así no chocan si conviven en la página),
     $estilo ('dialogo' | 'hoy'). Opcionales: $valor (lo elegido), $proyectos, $columnasOrden. --}}
@php
    $enDialogo = $estilo === 'dialogo';
    $nombre = ['proyecto' => 'proyecto', 'prioridad' => 'prioridad', 'columna' => 'columna_id'][$campo];
    $id = $prefijo.'-'.$campo;
    $idError = $prefijo.'-error-'.$nombre;
    $mensaje = $enDialogo ? null : $errors->first($nombre);
    $valor = $valor ?? null;
    $columnaInicial = $campo === 'columna'
        ? ($columnasOrden->firstWhere('categoria', \App\Enums\EstadoTarea::Pendiente) ?? $columnasOrden->first())
        : null;
    $claseControl = ($campo === 'proyecto' ? 'form-control' : 'form-select');
    $claseControl = $enDialogo ? $claseControl : 'hoy-entrada'.($mensaje ? ' es-invalido' : '');
@endphp
<div class="{{ $enDialogo ? 'dialogo-campo' : 'hoy-campo hoy-campo-tarea hoy-campo-tarea-'.$campo }}">
    @switch($campo)
        @case('proyecto')
            <label for="{{ $id }}" class="{{ $enDialogo ? 'dialogo-etiqueta' : '' }}">Proyecto @if ($enDialogo)<span class="dialogo-opcional">(opcional)</span>@endif</label>
            <input type="text" id="{{ $id }}" name="proyecto" class="{{ $claseControl }}" maxlength="255" list="{{ $prefijo }}-lista-proyectos" autocomplete="off"
                   @unless ($enDialogo) value="{{ $valor }}" @endunless
                   @if ($mensaje) aria-invalid="true" @endif aria-describedby="{{ $idError }}">
            <datalist id="{{ $prefijo }}-lista-proyectos">
                @foreach ($proyectos as $proyecto)
                    <option value="{{ $proyecto }}">
                @endforeach
            </datalist>
            @break
        @case('prioridad')
            <label for="{{ $id }}" class="{{ $enDialogo ? 'dialogo-etiqueta' : '' }}">Prioridad</label>
            <select id="{{ $id }}" name="prioridad" class="{{ $claseControl }}" required @if ($mensaje) aria-invalid="true" @endif aria-describedby="{{ $idError }}">
                @foreach (\App\Enums\PrioridadTarea::cases() as $prioridad)
                    <option value="{{ $prioridad->value }}" data-por-defecto="{{ $prioridad === \App\Enums\PrioridadTarea::Media ? 'true' : 'false' }}"
                            @selected($prioridad === (\App\Enums\PrioridadTarea::tryFrom((string) $valor) ?? \App\Enums\PrioridadTarea::Media))>{{ $prioridad->etiqueta() }}</option>
                @endforeach
            </select>
            @break
        @case('columna')
            <label for="{{ $id }}" class="{{ $enDialogo ? 'dialogo-etiqueta' : '' }}">Columna</label>
            <select id="{{ $id }}" name="columna_id" class="{{ $claseControl }}" required @if ($mensaje) aria-invalid="true" @endif aria-describedby="{{ $idError }}">
                @foreach ($columnasOrden as $columna)
                    <option value="{{ $columna->id }}" data-por-defecto="{{ $columna->is($columnaInicial) ? 'true' : 'false' }}"
                            @selected($enDialogo ? false : (string) ($valor ?? $columnaInicial?->id) === (string) $columna->id)>{{ $columna->nombre }}@if (mb_strtolower($columna->nombre) !== mb_strtolower($columna->categoria->etiqueta())) ({{ $columna->categoria->etiqueta() }})@endif</option>
                @endforeach
            </select>
            @break
    @endswitch
    @if ($enDialogo)
        <div class="dialogo-error" id="{{ $idError }}" data-error="{{ $nombre }}"></div>
    @else
        <p class="hoy-error" id="{{ $idError }}" @unless ($mensaje) hidden @endunless>{{ $mensaje }}</p>
    @endif
</div>
