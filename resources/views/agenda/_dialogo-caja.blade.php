{{-- Opciones de la caja abierta: actividad, horario, formato, borde, hecha, eliminar. Requiere: $actividades --}}
<dialog id="dialogo-caja" class="dialogo" aria-labelledby="dialogo-caja-titulo">
    <div class="dialogo-cuerpo">
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-caja-titulo">Opciones de la caja</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <p class="dialogo-subtitulo" data-dc="titulo-caja"></p>

        <fieldset class="dialogo-grupo">
            <legend>Actividad</legend>
            <div class="paleta-actividades">
                <label class="paleta-opcion paleta-sin-color">
                    <input type="radio" name="dc-actividad" value="">
                    <span class="paleta-nombre">Sin actividad</span>
                </label>
                @foreach ($actividades as $actividad)
                    <label class="paleta-opcion {{ $actividad->colorActividad()?->clase() }}">
                        <input type="radio" name="dc-actividad" value="{{ $actividad->id }}">
                        <span class="paleta-punto" aria-hidden="true"></span>
                        <span class="paleta-nombre">{{ $actividad->nombre }}</span>
                    </label>
                @endforeach
            </div>
            <p class="dialogo-ayuda">
                @if ($actividades->isEmpty())
                    Todavía no hay actividades.
                @endif
                <button type="button" class="dialogo-enlace" data-abrir-dialogo="dialogo-actividades">Gestionar actividades</button>
            </p>
        </fieldset>

        <fieldset class="dialogo-grupo">
            <legend>Horario <span class="dialogo-opcional">(opcional)</span></legend>
            <div class="dialogo-fila">
                <label class="dialogo-campo">Desde
                    <input type="time" class="form-control" data-dc="hora_inicio">
                </label>
                <label class="dialogo-campo">Hasta
                    <input type="time" class="form-control" data-dc="hora_fin">
                </label>
            </div>
            <p class="dialogo-error" data-dc="error-horas" role="alert"></p>
            <p class="dialogo-ayuda">Una caja con hora aparece en el planner como una línea, por ejemplo "18:00 Otorrino".</p>
        </fieldset>

        <fieldset class="dialogo-grupo">
            <legend>Formato</legend>
            <div class="dialogo-fila dialogo-opciones">
                @foreach (\App\Enums\TipoCaja::cases() as $tipo)
                    <label class="form-check">
                        <input type="radio" class="form-check-input" name="dc-tipo" value="{{ $tipo->value }}">
                        <span class="form-check-label">{{ $tipo->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="dialogo-grupo dialogo-borde">
            <legend>Borde</legend>
            <div class="borde-fila">
                <div class="borde-grosor" role="radiogroup" aria-label="Grosor del borde">
                    @foreach ([1 => 'Fino', 2 => 'Medio', 3 => 'Grueso', 4 => 'Extra'] as $px => $nombre)
                        <label class="borde-grosor-opcion" title="{{ $nombre }} ({{ $px }} px)">
                            <input type="radio" name="dc-borde-grosor" value="{{ $px }}" aria-label="{{ $nombre }}, {{ $px }} px">
                            <span>{{ $nombre }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="actividad-colores borde-colores" role="radiogroup" aria-label="Color del borde">
                <label class="actividad-color borde-auto" title="Automático: el color de la actividad o el terracota del cuaderno">
                    <input type="radio" name="dc-borde-color" value="" aria-label="Automático">
                    <span class="actividad-color-punto" aria-hidden="true"><i class="bi bi-dash-lg"></i></span>
                </label>
                @foreach (\App\Enums\ColorActividad::cases() as $color)
                    <label class="actividad-color {{ $color->clase() }}" title="{{ $color->etiqueta() }}">
                        <input type="radio" name="dc-borde-color" value="{{ $color->value }}" aria-label="{{ $color->etiqueta() }}">
                        <span class="actividad-color-punto" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="form-check dialogo-hecha">
            <input type="checkbox" class="form-check-input" id="dc-hecha" data-dc="hecha">
            <label class="form-check-label" for="dc-hecha">Caja hecha</label>
        </div>

        {{-- Mover y cambiar el tamaño: con el mouse o desde el agarre con el teclado. En una sola columna (móvil) solo se puede reordenar desde acá. --}}
        <div class="dialogo-botones dialogo-orden" role="group" aria-label="Orden de la caja en la hoja">
            <button type="button" class="btn btn-foco-suave btn-sm" data-orden="-1"><i class="bi bi-arrow-up" aria-hidden="true"></i> Subir en la hoja</button>
            <button type="button" class="btn btn-foco-suave btn-sm" data-orden="1"><i class="bi bi-arrow-down" aria-hidden="true"></i> Bajar en la hoja</button>
        </div>

        <div class="dialogo-pie">
            <button type="button" class="btn btn-foco-peligro btn-sm" data-eliminar><i class="bi bi-trash" aria-hidden="true"></i> Eliminar caja</button>
            <button type="button" class="btn btn-foco" data-cerrar-dialogo>Listo</button>
        </div>
    </div>
</dialog>
