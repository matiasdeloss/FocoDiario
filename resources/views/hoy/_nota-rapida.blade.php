{{-- Nota rápida. Se reemplaza a sí misma con HTMX. Opcionales: $guardada (Nota recién guardada), $valores (datos a conservar tras un error). Requiere: $destinos --}}
@php
    use App\Enums\ColorNota;

    $contextoElegido = $valores['contexto_id'] ?? ($guardada ?? null)?->contexto_id ?? null;
    $colorElegido = ColorNota::tryFrom((string) ($valores['color'] ?? ''));
    $contenido = $valores['contenido'] ?? '';
@endphp
<section id="nota-rapida" class="hoy-tarjeta hoy-nota" aria-labelledby="nota-rapida-titulo" data-nota-rapida>
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="nota-rapida-titulo">Nota rápida</h2>
        <span class="hoy-meta" data-nota-contador>{{ mb_strlen($contenido) }} {{ mb_strlen($contenido) === 1 ? 'carácter' : 'caracteres' }}</span>
    </div>

    @isset($guardada)
        <div class="hoy-aviso" role="status">
            <i class="bi bi-check2" aria-hidden="true"></i>
            Nota guardada {{ $guardada->contexto ? 'en ' . $guardada->contexto->rutaCompleta() : 'en la bandeja de entrada' }}.
        </div>
    @endisset

    <form method="POST" action="{{ route('notas.store') }}" novalidate class="hoy-nota-form"
          hx-post="{{ route('notas.store') }}" hx-target="#nota-rapida" hx-swap="outerHTML">
        @csrf
        <input type="hidden" name="origen" value="hoy">
        <input type="hidden" name="color" value="{{ $colorElegido?->value }}" data-nota-color-valor>

        <div class="hoy-nota-area">
            <label for="nota-rapida-contenido" class="hoy-solo-lector">¿Qué querés anotar?</label>
            <textarea id="nota-rapida-contenido" name="contenido" rows="5" maxlength="5000"
                      class="hoy-nota-texto @error('contenido') es-invalido @enderror"
                      placeholder="Escribí lo que tengas en mente…"
                      @if ($colorElegido) data-color="{{ $colorElegido->value }}" @endif
                      @error('contenido') aria-invalid="true" aria-describedby="nota-rapida-error" @enderror
                      @if (isset($guardada) || $errors->any()) autofocus @endif>{{ $contenido }}</textarea>
            @error('contenido') <p class="hoy-error" id="nota-rapida-error">{{ $message }}</p> @enderror
        </div>

        <div class="hoy-nota-campos">
            <div class="hoy-campo hoy-campo-destino">
                <label for="nota-rapida-destino">Destino</label>
                <select id="nota-rapida-destino" name="contexto_id" class="hoy-entrada @error('contexto_id') es-invalido @enderror">
                    <option value="">Bandeja de entrada</option>
                    @foreach ($destinos as $id => $ruta)
                        <option value="{{ $id }}" @selected((string) $contextoElegido === (string) $id)>{{ $ruta }}</option>
                    @endforeach
                </select>
                @error('contexto_id') <p class="hoy-error">{{ $message }}</p> @enderror
            </div>
            <div class="hoy-campo hoy-campo-fecha">
                <label for="nota-rapida-fecha">Fecha (opcional)</label>
                <input type="date" id="nota-rapida-fecha" name="fecha" value="{{ $valores['fecha'] ?? '' }}"
                       class="hoy-entrada @error('fecha') es-invalido @enderror">
                @error('fecha') <p class="hoy-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="hoy-nota-pie">
            <div class="hoy-colores" role="group" aria-label="Color de la nota">
                @foreach (ColorNota::cases() as $color)
                    <button type="button" class="hoy-color hoy-color-{{ $color->value }}" data-color-nota="{{ $color->value }}"
                            aria-label="Color {{ mb_strtolower($color->etiqueta()) }}" aria-pressed="{{ $colorElegido === $color ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            @error('color') <p class="hoy-error">{{ $message }}</p> @enderror
            <div class="hoy-nota-acciones">
                <button type="button" class="hoy-boton-texto" data-nota-limpiar>Limpiar</button>
                <button type="submit" class="hoy-boton"><i class="bi bi-plus-lg" aria-hidden="true"></i> Guardar</button>
            </div>
        </div>
    </form>
</section>
