@php
    $categorias = \App\Enums\EstadoTarea::cases();
    $etiquetasColumnas = $columnasOrden->mapWithKeys(fn ($c) => [$c->id => $c->nombre]);
@endphp
<div id="tablero" data-url-columna="{{ url('tareas/__ID__/columna') }}"
     data-orden="{{ json_encode($columnasOrden->pluck('id')) }}"
     data-etiquetas="{{ json_encode($etiquetasColumnas) }}"
     data-categorias="{{ json_encode($columnasOrden->mapWithKeys(fn ($c) => [$c->id => $c->categoria->value])) }}">
    <div id="tablero-aviso" class="aviso-foco" role="alert" hidden></div>
    @if ($errors->any())
        <div class="aviso-foco" role="alert">
            @foreach ($errors->all() as $mensajeError)
                <div>{{ $mensajeError }}</div>
            @endforeach
        </div>
    @endif

    <div class="tablero">
        @foreach ($columnas as $indice => $datos)
            @php
                $columna = $datos['columna'];
                $categoria = $columna->categoria;
                $esUnica = $columnasOrden->where('categoria', $categoria)->count() === 1;
                $obligatoria = $esUnica && $categoria !== \App\Enums\EstadoTarea::EnProgreso;
                $puedeQuitarse = $columnasOrden->count() > 1 && ! $obligatoria;
                $otras = $columnasOrden->where('id', '!=', $columna->id);
                $datosColumna = [
                    'id' => $columna->id,
                    'nombre' => $columna->nombre,
                    'categoria' => $categoria->value,
                    'categoriaEtiqueta' => $categoria->etiqueta(),
                    'obligatoria' => $obligatoria,
                    'tareas' => $columna->tareas_count ?? $columna->tareas()->count(),
                    'urlEditar' => route('tablero.columnas.update', $columna),
                    'urlEliminar' => route('tablero.columnas.destroy', $columna),
                    'otras' => $otras->map(fn ($o) => ['id' => $o->id, 'nombre' => $o->nombre])->values(),
                ];
            @endphp
            <section class="tablero-columna" aria-labelledby="col-{{ $columna->id }}" data-columna="{{ $columna->id }}" data-categoria="{{ $categoria->value }}">
                <header class="tablero-columna-cabecera">
                    <h2 id="col-{{ $columna->id }}" class="tablero-columna-titulo"><i class="bi {{ $categoria->icono() }}"></i> <span>{{ $columna->nombre }}</span></h2>
                    <span class="k-cuenta" data-contador data-ocultas="{{ $datos['ocultas'] }}">{{ $datos['tareas']->count() + $datos['ocultas'] }}</span>
                    <details class="tablero-menu">
                        <summary class="btn-icono" aria-label="Opciones de la columna {{ $columna->nombre }}" title="Opciones"><i class="bi bi-three-dots"></i></summary>
                        <div class="tablero-menu-cuerpo">
                            <button type="button" class="tablero-menu-accion" hidden data-requiere-js data-abrir-columna="editar"
                                    data-columna-datos="{{ json_encode($datosColumna, JSON_UNESCAPED_UNICODE) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Editar columna</button>
                            <noscript>
                            <form method="POST" action="{{ route('tablero.columnas.update', $columna) }}" class="tablero-form">
                                @csrf
                                @method('PATCH')
                                <label class="form-label" for="nombre-{{ $columna->id }}">Nombre</label>
                                <input id="nombre-{{ $columna->id }}" name="nombre" class="form-control form-control-sm" maxlength="60" required value="{{ $columna->nombre }}">
                                <label class="form-label" for="tipo-{{ $columna->id }}">Cuenta como</label>
                                <select id="tipo-{{ $columna->id }}" name="categoria" class="form-select form-select-sm" @disabled($obligatoria)>
                                    @foreach ($categorias as $opcion)
                                        <option value="{{ $opcion->value }}" @selected($opcion === $categoria)>{{ $opcion->etiqueta() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-foco-suave btn-sm">Guardar</button>
                            </form>
                            </noscript>
                            <form method="POST" action="{{ route('tablero.columnas.mover', $columna) }}" class="tablero-menu-fila">
                                @csrf
                                @method('PATCH')
                                <span>Posición</span>
                                <button type="submit" name="direccion" value="izquierda" class="btn-icono" title="Mover a la izquierda" aria-label="Mover {{ $columna->nombre }} a la izquierda" @disabled($indice === 0)><i class="bi bi-arrow-left"></i></button>
                                <button type="submit" name="direccion" value="derecha" class="btn-icono" title="Mover a la derecha" aria-label="Mover {{ $columna->nombre }} a la derecha" @disabled($indice === $columnas->count() - 1)><i class="bi bi-arrow-right"></i></button>
                            </form>
                            @if ($puedeQuitarse)
                                <button type="button" class="tablero-menu-accion peligro" hidden data-requiere-js data-abrir-columna="eliminar"
                                        data-columna-datos="{{ json_encode($datosColumna, JSON_UNESCAPED_UNICODE) }}"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar columna</button>
                                <noscript>
                                <form method="POST" action="{{ route('tablero.columnas.destroy', $columna) }}" class="tablero-form"
                                      data-confirmar="¿Eliminar la columna &quot;{{ $columna->nombre }}&quot;? Sus tareas pasan a la columna elegida.">
                                    @csrf
                                    @method('DELETE')
                                    <label class="form-label" for="reasignar-{{ $columna->id }}">Al eliminar, pasar sus tareas a</label>
                                    <select id="reasignar-{{ $columna->id }}" name="reasignar_a" class="form-select form-select-sm">
                                        @foreach ($otras as $otra)
                                            <option value="{{ $otra->id }}">{{ $otra->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-foco-suave btn-sm"><i class="bi bi-trash"></i> Eliminar columna</button>
                                </form>
                                </noscript>
                            @else
                                <p class="tablero-menu-nota">No se puede eliminar: el tablero necesita al menos una columna de tipo {{ $categoria->etiqueta() }}.</p>
                            @endif
                        </div>
                    </details>
                </header>
                <div class="tablero-lista" data-lista>
                    @foreach ($datos['tareas'] as $tarea)
                        @include('tablero._tarjeta')
                    @endforeach
                </div>
                <p class="estado-vacio tablero-vacio" data-vacio @if ($datos['tareas']->isNotEmpty()) hidden @endif>
                    @if ($categoria === \App\Enums\EstadoTarea::Completada)
                        Todavía no completaste ninguna. Arrastrá una tarea acá cuando la termines.
                    @else
                        Sin tareas. Arrastrá una acá o añadí una tarjeta.
                    @endif
                </p>
                @if ($datos['ocultas'] > 0)
                    <a class="tablero-ver-todas" href="{{ route('tareas.index', ['estado' => 'hechas']) }}">
                        Ver todas las completadas ({{ $datos['tareas']->count() + $datos['ocultas'] }})
                    </a>
                @endif
                <div class="k-anadir" hidden data-requiere-js data-anadir data-url="{{ route('tablero.columnas.tarjetas.store', $columna) }}">
                    <button type="button" class="tablero-anadir-boton" data-anadir-abrir aria-label="Añadir tarjeta a {{ $columna->nombre }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Añadir tarjeta</button>
                    <form class="k-anadir-form" hidden data-anadir-form novalidate>
                        <label class="visually-hidden" for="k-nueva-{{ $columna->id }}">Título de la nueva tarjeta en {{ $columna->nombre }}</label>
                        <textarea id="k-nueva-{{ $columna->id }}" name="titulo" rows="2" maxlength="255" class="form-control" placeholder="Título de la tarjeta…" autocomplete="off" enterkeyhint="done"></textarea>
                        <p class="k-anadir-error" role="alert" data-anadir-error></p>
                        <div class="k-anadir-acciones">
                            <button type="submit" class="btn btn-foco btn-sm" data-anadir-enviar>Añadir</button>
                            <button type="button" class="btn-icono" data-anadir-cerrar aria-label="Cancelar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </div>
                    </form>
                </div>
                <noscript>
                <details class="tablero-anadir">
                    <summary><i class="bi bi-plus-lg"></i> Añadir tarjeta</summary>
                    <form method="POST" action="{{ route('tablero.columnas.tarjetas.store', $columna) }}" class="tablero-form">
                        @csrf
                        <label class="visually-hidden" for="nueva-{{ $columna->id }}">Título de la nueva tarjeta</label>
                        <input id="nueva-{{ $columna->id }}" name="titulo" class="form-control form-control-sm" maxlength="255" required placeholder="Título de la tarjeta">
                        <button type="submit" class="btn btn-foco btn-sm">Añadir</button>
                    </form>
                </details>
                </noscript>
            </section>
        @endforeach

        <div class="tablero-columna tablero-columna-nueva">
            <button type="button" class="tablero-anadir-boton" hidden data-requiere-js data-abrir-columna="nueva"><i class="bi bi-plus-lg" aria-hidden="true"></i> Añadir columna</button>
            <noscript>
            <details class="tablero-anadir">
                <summary><i class="bi bi-plus-lg"></i> Añadir columna</summary>
                <form method="POST" action="{{ route('tablero.columnas.store') }}" class="tablero-form">
                    @csrf
                    <label class="visually-hidden" for="columna-nueva-nombre">Nombre de la nueva columna</label>
                    <input id="columna-nueva-nombre" name="nombre" class="form-control form-control-sm" maxlength="60" required placeholder="Nombre de la columna">
                    <label class="form-label" for="columna-nueva-tipo">Cuenta como</label>
                    <select id="columna-nueva-tipo" name="categoria" class="form-select form-select-sm">
                        @foreach ($categorias as $opcion)
                            <option value="{{ $opcion->value }}" @selected($opcion === \App\Enums\EstadoTarea::EnProgreso)>{{ $opcion->etiqueta() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-foco btn-sm">Añadir columna</button>
                </form>
            </details>
            </noscript>
        </div>
    </div>
</div>
