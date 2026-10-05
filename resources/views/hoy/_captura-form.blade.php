{{-- Formulario de captura rápida, con aspecto de cuaderno (se reemplaza a sí mismo con HTMX). Requiere: $destinos. Opcionales: $valores (lo escrito), $guardada, $recienGuardado, $contextosTarea y $columnasOrden (campos de la tarea; los pone el composer si faltan). --}}
@php
    use App\Enums\ColorActividad;
    use App\Enums\PrioridadTarea;
    use App\Services\Hoy\CapturaRapida;

    $valores = ($valores ?? []) ?: old();
    $tipo = in_array($valores['tipo'] ?? null, ['tarea', 'recordatorio', 'nota'], true) ? $valores['tipo'] : 'nota';
    $tipos = ['nota' => 'Nota', 'tarea' => 'Tarea', 'recordatorio' => 'Recordatorio'];
    $titulo = $valores['titulo'] ?? '';
    // La ruta de notas (origen=hoy) manda el texto como "contenido".
    $descripcion = $valores['descripcion'] ?? $valores['contenido'] ?? '';
    $fecha = $valores['fecha'] ?? '';
    $hora = $valores['hora'] ?? '';
    $contextoElegido = $valores['contexto_id'] ?? ($guardada ?? null)?->contexto_id ?? null;
    $colorElegido = ColorActividad::tryFrom((string) ($valores['color'] ?? ''));
    $errorDescripcion = $errors->first('descripcion') ?: $errors->first('contenido');
    $enfocarTitulo = ! empty($recienGuardado) || ! empty($guardada) || $errors->has('titulo');

    // Lo que muestra cada chip cuando tiene valor.
    $textoFecha = CapturaRapida::etiquetaFecha($fecha, $tipo === 'recordatorio' ? $hora : null);
    // Campos propios de la tarea (los mismos del modal de tareas): contexto, prioridad y columna.
    $contextoTareaElegido = $valores['tarea_contexto_id'] ?? '';
    $nombreContextoTarea = null;
    foreach ($contextosTarea as $grupo) {
        $nombreContextoTarea ??= $grupo[$contextoTareaElegido] ?? null;
    }
    $prioridadElegida = PrioridadTarea::tryFrom((string) ($valores['prioridad'] ?? ''));
    $textoTarea = collect([
        $prioridadElegida && $prioridadElegida !== PrioridadTarea::Media ? $prioridadElegida->etiqueta() : null,
        $nombreContextoTarea,
    ])->filter()->implode(' · ');
    $hayDestino = $contextoElegido !== null && $contextoElegido !== '' && isset($destinos[$contextoElegido]);
    $textoDestino = $hayDestino ? $destinos[$contextoElegido] : 'Bandeja de entrada';

    // Un error en un campo de un chip cerrado abre ese chip para que se vea.
    $abierto = [
        'fecha' => $errors->has('fecha') || $errors->has('hora'),
        'destino' => $errors->has('contexto_id'),
        'tarea' => $errors->hasAny(['tarea_contexto_id', 'prioridad', 'columna_id']),
    ];
    $para = ['fecha' => 'tarea recordatorio nota', 'destino' => 'nota', 'tarea' => 'tarea'];
    $oculto = fn (string $clave) => ! in_array($tipo, explode(' ', $para[$clave]), true);
@endphp
<form id="nota-rapida-form" method="POST" action="{{ route('hoy.captura') }}" novalidate class="hoy-nota-form" data-captura
      @if (! empty($recienGuardado) || ! empty($guardada)) data-recien-guardado @endif
      @if (! empty($valores['tipo'])) data-tipo-servidor @endif
      @if (in_array($tipo, ['nota', 'tarea'], true) && $colorElegido) data-color="{{ $colorElegido->clave() }}" @endif
      hx-post="{{ route('hoy.captura') }}" hx-target="this" hx-swap="outerHTML">
    @csrf
    <input type="hidden" name="color" value="{{ $colorElegido?->value }}" data-nota-color-valor>

    {{-- Cabecera: título a la izquierda y los colores siempre a la vista a la derecha (el color se guarda en las notas y las tareas). --}}
    <div class="hoy-nota-cab">
        <h2 class="hoy-tarjeta-titulo" id="nota-rapida-titulo">Nota rápida</h2>
        <div class="hoy-colores hoy-solo-js" role="group" aria-label="Color de la nota o la tarea" aria-describedby="nota-rapida-color-ayuda"
             title="El color se guarda en las notas y las tareas">
            <button type="button" class="hoy-color hoy-color-ninguno" data-color-nota="" data-color-clave=""
                    aria-label="Sin color (usa el del contexto)" title="Sin color (usa el del contexto)" aria-pressed="{{ $colorElegido === null ? 'true' : 'false' }}"></button>
            @foreach (ColorActividad::cases() as $color)
                <button type="button" class="hoy-color hoy-color-{{ $color->clave() }}" data-color-nota="{{ $color->value }}" data-color-clave="{{ $color->clave() }}"
                        aria-label="Color {{ mb_strtolower($color->etiqueta()) }}" title="{{ $color->etiqueta() }}" aria-pressed="{{ $colorElegido === $color ? 'true' : 'false' }}"></button>
            @endforeach
            <span class="hoy-solo-lector" id="nota-rapida-color-ayuda">El color se guarda en las notas y las tareas.</span>
        </div>
    </div>
    @error('color') <p class="hoy-error">{{ $message }}</p> @enderror

    <div class="hoy-cuaderno">
        <label class="hoy-solo-lector" for="nota-rapida-titulo-campo">Título</label>
        <input type="text" id="nota-rapida-titulo-campo" name="titulo" value="{{ $titulo }}" maxlength="255"
               class="hoy-cuaderno-titulo @error('titulo') es-invalido @enderror" placeholder="Anotá algo…"
               autocomplete="off" enterkeyhint="done" required
               @error('titulo') aria-invalid="true" aria-describedby="nota-rapida-error-titulo" @enderror
               @if ($enfocarTitulo) autofocus @endif>
        @error('titulo') <p class="hoy-error" id="nota-rapida-error-titulo">{{ $message }}</p> @enderror

        <label class="hoy-solo-lector" for="nota-rapida-descripcion">Descripción (opcional)</label>
        <textarea id="nota-rapida-descripcion" name="descripcion" rows="1" maxlength="5000"
                  class="hoy-cuaderno-hoja @if ($errorDescripcion) es-invalido @endif"
                  placeholder="Detalles o contexto…"
                  @if ($errorDescripcion) aria-invalid="true" aria-describedby="nota-rapida-error-descripcion" @endif>{{ $descripcion }}</textarea>
        @if ($errorDescripcion) <p class="hoy-error" id="nota-rapida-error-descripcion">{{ $errorDescripcion }}</p> @endif
        {{-- Va al final del cuaderno en el orden del teclado (Tab pasa del título a la descripción); se dibuja arriba a la derecha por CSS. --}}
        <button type="button" class="hoy-descartar hoy-solo-js" data-nota-descartar aria-label="Descartar lo escrito" title="Descartar (Esc)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>

    <div class="hoy-nota-pie">
        <fieldset class="hoy-tipos-grupo">
            <legend class="hoy-solo-lector">Tipo</legend>
            <div class="hoy-tipos">
                @foreach ($tipos as $valor => $etiqueta)
                    <label class="hoy-tipo">
                        <input type="radio" name="tipo" value="{{ $valor }}" @checked($tipo === $valor) data-tipo>
                        <span>{{ $etiqueta }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="hoy-chips hoy-solo-js">
            {{-- Fecha (y hora en los recordatorios) --}}
            <span class="hoy-chip-grupo {{ $abierto['fecha'] ? 'es-error' : '' }}" data-chip-grupo="fecha" data-para="{{ $para['fecha'] }}" @if ($oculto('fecha')) data-oculto @endif>
                <button type="button" class="hoy-chip" data-chip="fecha" aria-expanded="{{ $abierto['fecha'] ? 'true' : 'false' }}" aria-controls="nota-rapida-panel-fecha"
                        aria-label="Fecha{{ $textoFecha !== '' ? ': ' . $textoFecha : '' }}">
                    <i class="bi bi-calendar3" aria-hidden="true"></i><span class="hoy-chip-texto" data-chip-texto>{{ $textoFecha }}</span>
                </button>
                <button type="button" class="hoy-chip-x" data-chip-quitar="fecha" aria-label="Quitar la fecha" @if ($textoFecha === '') hidden @endif><i class="bi bi-x" aria-hidden="true"></i></button>
            </span>
            {{-- Destino (solo notas) --}}
            <span class="hoy-chip-grupo {{ $abierto['destino'] ? 'es-error' : '' }}" data-chip-grupo="destino" data-para="{{ $para['destino'] }}" @if ($oculto('destino')) data-oculto @endif>
                <button type="button" class="hoy-chip" data-chip="destino" aria-expanded="{{ $abierto['destino'] ? 'true' : 'false' }}" aria-controls="nota-rapida-panel-destino"
                        aria-label="Destino: {{ $textoDestino }}">
                    <i class="bi bi-folder2" aria-hidden="true"></i><span class="hoy-chip-texto" data-chip-texto>{{ $textoDestino }}</span>
                </button>
                <button type="button" class="hoy-chip-x" data-chip-quitar="destino" aria-label="Volver a la bandeja de entrada" @unless ($hayDestino) hidden @endunless><i class="bi bi-x" aria-hidden="true"></i></button>
            </span>
            {{-- Más detalles de la tarea: contexto, prioridad y columna --}}
            <span class="hoy-chip-grupo {{ $abierto['tarea'] ? 'es-error' : '' }}" data-chip-grupo="tarea" data-para="{{ $para['tarea'] }}" @if ($oculto('tarea')) data-oculto @endif>
                <button type="button" class="hoy-chip" data-chip="tarea" aria-expanded="{{ $abierto['tarea'] ? 'true' : 'false' }}" aria-controls="nota-rapida-panel-tarea"
                        aria-label="Más detalles de la tarea{{ $textoTarea !== '' ? ': ' . $textoTarea : '' }}">
                    <i class="bi bi-sliders" aria-hidden="true"></i><span class="hoy-chip-texto" data-chip-texto>{{ $textoTarea !== '' ? $textoTarea : 'Más detalles' }}</span>
                </button>
            </span>
        </div>

        <button type="submit" class="hoy-boton hoy-guardar"><i class="bi bi-plus-lg" aria-hidden="true"></i> Guardar</button>
    </div>

    <div class="hoy-paneles">
        <div class="hoy-panel {{ $abierto['fecha'] ? 'es-abierto' : '' }}" id="nota-rapida-panel-fecha" data-panel="fecha" role="group" aria-label="Fecha" data-para="{{ $para['fecha'] }}" @if ($oculto('fecha')) data-oculto @endif>
            <div class="hoy-panel-campos">
                <div class="hoy-campo hoy-campo-fecha">
                    <label for="nota-rapida-fecha">Fecha</label>
                    <input type="date" id="nota-rapida-fecha" name="fecha" value="{{ $fecha }}"
                           class="hoy-entrada @error('fecha') es-invalido @enderror"
                           @error('fecha') aria-invalid="true" aria-describedby="nota-rapida-error-fecha" @enderror>
                </div>
                <div class="hoy-campo hoy-campo-hora" data-para="recordatorio" @if ($tipo !== 'recordatorio') data-oculto @endif>
                    <label for="nota-rapida-hora">Hora</label>
                    <input type="time" id="nota-rapida-hora" name="hora" value="{{ $hora }}"
                           class="hoy-entrada @error('hora') es-invalido @enderror"
                           @error('hora') aria-invalid="true" aria-describedby="nota-rapida-error-hora" @enderror>
                </div>
                <div class="hoy-atajos hoy-solo-js">
                    <button type="button" class="hoy-atajo" data-fecha-atajo="0">Hoy</button>
                    <button type="button" class="hoy-atajo" data-fecha-atajo="1">Mañana</button>
                </div>
            </div>
            @error('fecha') <p class="hoy-error" id="nota-rapida-error-fecha">{{ $message }}</p> @enderror
            @error('hora') <p class="hoy-error" id="nota-rapida-error-hora">{{ $message }}</p> @enderror
        </div>

        <div class="hoy-panel {{ $abierto['destino'] ? 'es-abierto' : '' }}" id="nota-rapida-panel-destino" data-panel="destino" role="group" aria-label="Destino" data-para="{{ $para['destino'] }}" @if ($oculto('destino')) data-oculto @endif>
            <div class="hoy-panel-campos">
                <div class="hoy-campo hoy-campo-destino">
                    <label for="nota-rapida-destino">Destino</label>
                    <select id="nota-rapida-destino" name="contexto_id" class="hoy-entrada @error('contexto_id') es-invalido @enderror"
                            @error('contexto_id') aria-invalid="true" aria-describedby="nota-rapida-error-destino" @enderror>
                        <option value="">Bandeja de entrada</option>
                        @foreach ($destinos as $id => $ruta)
                            <option value="{{ $id }}" @selected((string) $contextoElegido === (string) $id)>{{ $ruta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @error('contexto_id') <p class="hoy-error" id="nota-rapida-error-destino">{{ $message }}</p> @enderror
        </div>

        {{-- Los mismos campos propios que el modal de tareas (partial compartido, con otro prefijo de ids). --}}
        <div class="hoy-panel {{ $abierto['tarea'] ? 'es-abierto' : '' }}" id="nota-rapida-panel-tarea" data-panel="tarea" role="group" aria-label="Más detalles de la tarea" data-para="{{ $para['tarea'] }}" @if ($oculto('tarea')) data-oculto @endif>
            <div class="hoy-panel-campos">
                @include('tareas._campo-extra', ['campo' => 'contexto', 'prefijo' => 'nota-rapida-tarea', 'estilo' => 'hoy', 'valor' => $contextoTareaElegido, 'contextos' => $contextosTarea])
                @include('tareas._campo-extra', ['campo' => 'prioridad', 'prefijo' => 'nota-rapida-tarea', 'estilo' => 'hoy', 'valor' => $valores['prioridad'] ?? null])
                @if ($columnasOrden->isNotEmpty())
                    @include('tareas._campo-extra', ['campo' => 'columna', 'prefijo' => 'nota-rapida-tarea', 'estilo' => 'hoy', 'valor' => $valores['columna_id'] ?? null])
                @endif
            </div>
        </div>
    </div>
    @error('tipo') <p class="hoy-error">{{ $message }}</p> @enderror
</form>

