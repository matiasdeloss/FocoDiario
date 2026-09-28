{{-- Nota rápida. Se reemplaza a sí misma con HTMX. Opcionales: $guardada (Nota recién guardada), $valores (datos a conservar tras un error). Requiere: $destinos --}}
@php
    $contextoElegido = $valores['contexto_id'] ?? ($guardada ?? null)?->contexto_id ?? null;
@endphp
<section id="nota-rapida" class="tarjeta p-3 p-md-4 mb-3" aria-labelledby="nota-rapida-titulo">
    <h2 class="tarjeta-titulo d-flex justify-content-between" id="nota-rapida-titulo">
        Nota rápida
        <a href="{{ route('notas.index', ['contexto' => 'bandeja']) }}" class="text-decoration-none text-lowercase fw-normal">bandeja de entrada</a>
    </h2>

    @isset($guardada)
        <div class="aviso-foco nota-rapida-aviso" role="status">
            <i class="bi bi-check2"></i>
            Nota guardada {{ $guardada->contexto ? 'en ' . $guardada->contexto->rutaCompleta() : 'en la bandeja de entrada' }}.
        </div>
    @endisset

    <form method="POST" action="{{ route('notas.store') }}" novalidate
          hx-post="{{ route('notas.store') }}" hx-target="#nota-rapida" hx-swap="outerHTML">
        @csrf
        <input type="hidden" name="origen" value="hoy">

        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-5">
                <label for="nota-rapida-contenido" class="form-label">¿Qué querés anotar?</label>
                <input type="text" id="nota-rapida-contenido" name="contenido" value="{{ $valores['contenido'] ?? '' }}"
                       class="form-control @error('contenido') is-invalid @enderror" maxlength="5000"
                       placeholder="Escribí y apretá Enter" autocomplete="off" required
                       @if (isset($guardada) || $errors->any()) autofocus @endif>
                @error('contenido') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-7 col-lg-3">
                <label for="nota-rapida-destino" class="form-label">Destino</label>
                <select id="nota-rapida-destino" name="contexto_id" class="form-select @error('contexto_id') is-invalid @enderror">
                    <option value="">Bandeja de entrada</option>
                    @foreach ($destinos as $id => $ruta)
                        <option value="{{ $id }}" @selected((string) $contextoElegido === (string) $id)>{{ $ruta }}</option>
                    @endforeach
                </select>
                @error('contexto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-5 col-lg-2">
                <label for="nota-rapida-fecha" class="form-label">Fecha <span class="text-secondary fw-normal">(opcional)</span></label>
                <input type="date" id="nota-rapida-fecha" name="fecha" value="{{ $valores['fecha'] ?? '' }}"
                       class="form-control @error('fecha') is-invalid @enderror">
                @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button type="submit" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Guardar</button>
            </div>
        </div>
    </form>
</section>
