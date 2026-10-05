{{-- Caja de la hoja del día, como elemento de GridStack. Requiere: $caja. Opcional: $colores (App\Support\ColoresDeContexto, para no consultar por caja) --}}
@php
    // Color efectivo del contexto de la caja: el propio o el del ancestro más cercano con color.
    $clase = ($colores ?? \App\Support\ColoresDeContexto::delUsuario())->clase($caja->contexto_id);
    $items = $caja->itemsLista();
    // Una lista vacía muestra un primer renglón para empezar a escribir.
    if ($caja->tipo === \App\Enums\TipoCaja::Lista && $items === []) {
        $items = [['texto' => '', 'hecho' => false]];
    }
    $estado = [
        'id' => $caja->id,
        'tipo' => $caja->tipo->value,
        'contexto_id' => $caja->contexto_id,
        'titulo' => $caja->titulo,
        'contenido' => $caja->contenido,
        'items' => $items,
        'hora_inicio' => $caja->hora_inicio,
        'hora_fin' => $caja->hora_fin,
        'hecha' => $caja->hecha,
        'borde_grosor' => $caja->borde_grosor ?? 1,
        'borde_color' => $caja->borde_color,
    ];
@endphp
<div class="grid-stack-item" gs-id="{{ $caja->id }}" gs-x="{{ $caja->x }}" gs-y="{{ $caja->y }}" gs-w="{{ $caja->ancho }}" gs-h="{{ $caja->alto }}" gs-min-w="2" gs-min-h="4">
    <div class="grid-stack-item-content">
        <article class="caja {{ $clase }} {{ $caja->hecha ? 'es-hecha' : '' }}" data-caja data-metodo="PATCH"
                 @if (($caja->borde_grosor ?? 1) > 1 || $caja->borde_color) style="{{ ($caja->borde_grosor ?? 1) > 1 ? '--caja-borde-grosor: '.$caja->borde_grosor.'px;' : '' }}{{ $caja->borde_color ? ' --caja-borde-color: '.$caja->borde_color.';' : '' }}" @endif
                 data-url="{{ route('agenda.cajas.update', $caja) }}" data-estado="{{ json_encode($estado, JSON_UNESCAPED_UNICODE) }}"
                 data-etiqueta-contenido="Contenido de la caja {{ $caja->tituloVisible() }}">
            <header class="caja-cab">
                <span class="caja-agarre" data-agarre role="button" tabindex="0" title="Arrastrar para mover"
                      aria-label="Mover la caja: con las flechas se mueve y con Mayús más flechas cambia el tamaño"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
                <input type="text" class="caja-titulo" data-campo="titulo" value="{{ $caja->titulo }}" maxlength="255"
                       placeholder="Título" aria-label="Título de la caja" autocomplete="off" enterkeyhint="done">
                <div class="caja-acciones">
                    <span class="caja-hora" data-hora @if (! $caja->hora_inicio) hidden @endif>{{ $caja->horaTexto() }}</span>
                    <button type="button" class="caja-boton" data-accion="hecha" aria-pressed="{{ $caja->hecha ? 'true' : 'false' }}"
                            aria-label="Marcar como hecha" title="Marcar como hecha"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                    <button type="button" class="caja-boton" data-accion="menu" aria-haspopup="dialog"
                            aria-label="Opciones de la caja" title="Opciones de la caja"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                </div>
            </header>
            @include('agenda._hoja', [
                'tipo' => $caja->tipo->value,
                'contenido' => $caja->contenido,
                'items' => $items,
                'etiqueta' => 'Contenido de la caja '.$caja->tituloVisible(),
            ])
        </article>
    </div>
</div>
