{{-- Selector de tableros (pestañas) y su gestión: crear, renombrar, hacer principal y eliminar. Requiere: $tableros, $tableroActual.
     Todo con formularios comunes: funciona sin JavaScript. --}}
<nav class="k-tableros" aria-label="Tableros">
    <ul class="k-tableros-lista">
        @foreach ($tableros as $tablero)
            <li>
                <a href="{{ route('tablero.index', ['tablero' => $tablero->id]) }}" class="k-tablero-pestana" @if ($tablero->is($tableroActual)) aria-current="page" @endif>
                    {{ $tablero->nombre }}
                    @if ($tablero->principal)
                        <i class="bi bi-star-fill k-tablero-principal" aria-hidden="true"></i><span class="visually-hidden">(principal)</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <details class="k-gestion">
        <summary class="btn btn-foco-suave btn-sm"><i class="bi bi-gear" aria-hidden="true"></i> Gestionar tableros</summary>
        <div class="k-gestion-cuerpo">
            <form method="POST" action="{{ route('tableros.store') }}" class="k-gestion-form">
                @csrf
                <label for="tablero-nuevo" class="form-label">Nuevo tablero</label>
                <div class="k-gestion-fila">
                    <input id="tablero-nuevo" name="nombre" class="form-control form-control-sm" maxlength="60" required placeholder="Por ejemplo, Facultad">
                    <button type="submit" class="btn btn-foco btn-sm">Crear</button>
                </div>
            </form>

            <form method="POST" action="{{ route('tableros.update', $tableroActual) }}" class="k-gestion-form">
                @csrf
                @method('PATCH')
                <label for="tablero-nombre" class="form-label">Renombrar "{{ $tableroActual->nombre }}"</label>
                <div class="k-gestion-fila">
                    <input id="tablero-nombre" name="nombre" class="form-control form-control-sm" maxlength="60" required value="{{ $tableroActual->nombre }}">
                    <button type="submit" class="btn btn-foco-suave btn-sm">Guardar</button>
                </div>
            </form>

            @if ($tableroActual->principal)
                <p class="k-gestion-nota"><i class="bi bi-star-fill" aria-hidden="true"></i> Es el tablero principal: lo que creás fuera del tablero (Hoy, calendario, formularios) va a su columna "Sin asignar".</p>
            @else
                <form method="POST" action="{{ route('tableros.principal', $tableroActual) }}" class="k-gestion-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-foco-suave btn-sm"><i class="bi bi-star" aria-hidden="true"></i> Hacer principal</button>
                </form>
            @endif

            @if ($tableros->count() > 1)
                <form method="POST" action="{{ route('tableros.destroy', $tableroActual) }}" class="k-gestion-form"
                      data-confirmar="¿Eliminar el tablero &quot;{{ $tableroActual->nombre }}&quot;? Sus tarjetas pasan a &quot;Sin asignar&quot; del tablero que elijas.">
                    @csrf
                    @method('DELETE')
                    <label for="tablero-destino" class="form-label">Al eliminarlo, pasar sus tarjetas a</label>
                    <div class="k-gestion-fila">
                        <select id="tablero-destino" name="destino_id" class="form-select form-select-sm" required>
                            @foreach ($tableros->where('id', '!=', $tableroActual->id) as $otro)
                                <option value="{{ $otro->id }}" @selected($otro->principal)>{{ $otro->nombre }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-peligro-suave"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                    </div>
                </form>
            @else
                <p class="k-gestion-nota">Es tu único tablero: no se puede eliminar.</p>
            @endif
        </div>
    </details>
</nav>
