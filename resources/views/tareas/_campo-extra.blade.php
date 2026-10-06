{{-- Campo propio de una tarea (contexto, prioridad o columna), compartido por el modal de tareas y la nota rápida de Hoy.
     Requiere: $campo ('contexto' | 'prioridad' | 'columna'), $prefijo (prefijo de los ids: así no chocan si conviven en la página),
     $estilo ('dialogo' | 'hoy'). Opcionales: $valor (lo elegido), $contextos (agrupados por tipo, como Contexto::opcionesPorTipo()), $columnasOrden.
     El contexto viaja como contexto_id en el modal y como tarea_contexto_id en la nota rápida (ahí contexto_id es el destino de la nota). --}}
@php
    $enDialogo = $estilo === 'dialogo';
    $nombre = ['contexto' => $enDialogo ? 'contexto_id' : 'tarea_contexto_id', 'prioridad' => 'prioridad', 'columna' => 'columna_id'][$campo];
    $id = $prefijo.'-'.$campo;
    $idError = $prefijo.'-error-'.$nombre;
    $mensaje = $enDialogo ? null : $errors->first($nombre);
    $valor = $valor ?? null;
    // Las columnas de todos los tableros (cada tablero es un grupo): elegir la columna es elegir el tablero.
    // Por defecto, "Sin asignar" del tablero principal.
    $columnasSelector = $columnasTodas ?? $columnasOrden ?? collect();
    $columnaInicial = $campo === 'columna'
        ? ($columnasSelector->first(fn ($c) => $c->fija && $c->tablero?->principal) ?? $columnasSelector->firstWhere('fija', true) ?? $columnasSelector->first())
        : null;
    $claseControl = $enDialogo ? 'form-select' : 'hoy-entrada'.($mensaje ? ' es-invalido' : '');
@endphp
<div class="{{ $enDialogo ? 'dialogo-campo' : 'hoy-campo hoy-campo-tarea hoy-campo-tarea-'.$campo }}">
    @switch($campo)
        @case('contexto')
            <label for="{{ $id }}" class="{{ $enDialogo ? 'dialogo-etiqueta' : '' }}">Contexto @if ($enDialogo)<span class="dialogo-opcional">(opcional)</span>@endif</label>
            <select id="{{ $id }}" name="{{ $nombre }}" class="{{ $claseControl }}" @if ($mensaje) aria-invalid="true" @endif aria-describedby="{{ $idError }}">
                <option value="">Sin contexto</option>
                @include('tareas._opciones-contexto', ['grupos' => $contextos, 'elegido' => $enDialogo ? null : $valor, 'conColor' => $enDialogo, 'colores' => $colores ?? null])
            </select>
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
            <label for="{{ $id }}" class="{{ $enDialogo ? 'dialogo-etiqueta' : '' }}">Tablero y columna</label>
            <select id="{{ $id }}" name="columna_id" class="{{ $claseControl }}" required @if ($mensaje) aria-invalid="true" @endif aria-describedby="{{ $idError }}">
                @foreach ($columnasSelector->groupBy('tablero_id') as $delTablero)
                    <optgroup label="{{ $delTablero->first()->tablero?->nombre }}{{ $delTablero->first()->tablero?->principal ? ' (principal)' : '' }}">
                        @foreach ($delTablero as $columna)
                            <option value="{{ $columna->id }}" data-por-defecto="{{ $columna->is($columnaInicial) ? 'true' : 'false' }}"
                                    @selected($enDialogo ? false : (string) ($valor ?? $columnaInicial?->id) === (string) $columna->id)>{{ $columna->nombre }}@if (mb_strtolower($columna->nombre) !== mb_strtolower($columna->categoria->etiqueta())) ({{ $columna->categoria->etiqueta() }})@endif</option>
                        @endforeach
                    </optgroup>
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
