{{-- Gestión de actividades (materias con color) dentro de la agenda. Requiere: $actividades. --}}
@php
    use App\Enums\ColorActividad;

    $formularioConError = (string) old('_actividad', '');
    $abrir = $errors->any() && $formularioConError !== '';
@endphp
<dialog id="dialogo-actividades" class="dialogo" aria-labelledby="dialogo-actividades-titulo" @if ($abrir) data-abrir @endif>
    <div class="dialogo-cuerpo">
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-actividades-titulo">Actividades</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <p class="dialogo-ayuda">Cada materia o actividad tiene un color. Las cajas que le asignes se ven con ese color, y también podés elegirla como destino al anotar una nota rápida.</p>

        <ul class="actividades-lista">
            @forelse ($actividades as $actividad)
                @php
                    $enEdicion = $formularioConError === (string) $actividad->id;
                    $colorActual = strtolower((string) ($enEdicion ? old('color') : $actividad->color));
                @endphp
                <li>
                    <details class="actividad-item {{ $actividad->colorActividad()?->clase() }}" @if ($enEdicion) open @endif>
                        <summary class="actividad-resumen">
                            <span class="paleta-punto" aria-hidden="true"></span>
                            <span class="actividad-nombre">{{ $actividad->nombre }}</span>
                            <span class="actividad-editar">Editar</span>
                        </summary>
                        <form method="POST" action="{{ route('agenda.actividades.update', $actividad) }}" class="actividad-form" data-tras-guardar novalidate>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_actividad" value="{{ $actividad->id }}">
                            <label class="dialogo-campo" for="actividad-nombre-{{ $actividad->id }}">Nombre</label>
                            <input type="text" id="actividad-nombre-{{ $actividad->id }}" name="nombre" maxlength="80" required
                                   value="{{ $enEdicion ? old('nombre') : $actividad->nombre }}"
                                   class="form-control @if ($enEdicion && $errors->has('nombre')) is-invalid @endif">
                            @if ($enEdicion) @error('nombre') <p class="dialogo-error">{{ $message }}</p> @enderror @endif
                            <fieldset class="dialogo-grupo">
                                <legend>Color</legend>
                                <div class="paleta-actividades">
                                    @foreach (ColorActividad::cases() as $color)
                                        <label class="paleta-opcion {{ $color->clase() }}">
                                            <input type="radio" name="color" value="{{ $color->value }}" @checked($colorActual === $color->value)>
                                            <span class="paleta-punto" aria-hidden="true"></span>
                                            <span class="paleta-nombre">{{ $color->etiqueta() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if ($enEdicion) @error('color') <p class="dialogo-error">{{ $message }}</p> @enderror @endif
                            </fieldset>
                            <div class="dialogo-botones">
                                <button type="submit" class="btn btn-foco btn-sm">Guardar</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('agenda.actividades.destroy', $actividad) }}" class="actividad-borrar" data-tras-guardar
                              onsubmit="return confirm(@js('¿Eliminar "'.$actividad->nombre.'"? Sus cajas quedan sin actividad y las notas que la usaban pasan a la bandeja de entrada.'))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-foco-peligro btn-sm"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar actividad</button>
                        </form>
                    </details>
                </li>
            @empty
                <li class="dialogo-ayuda">Todavía no creaste actividades. Empezá con el formulario de abajo.</li>
            @endforelse
        </ul>

        @php
            $nuevaConError = $formularioConError === 'nueva';
            $colorNueva = strtolower((string) ($nuevaConError ? old('color') : ''));
        @endphp
        <form method="POST" action="{{ route('agenda.actividades.store') }}" class="actividad-form actividad-nueva" data-tras-guardar novalidate>
            @csrf
            <input type="hidden" name="_actividad" value="nueva">
            <h3 class="dialogo-subtitulo">Nueva actividad</h3>
            <label class="dialogo-campo" for="actividad-nombre-nueva">Nombre</label>
            <input type="text" id="actividad-nombre-nueva" name="nombre" maxlength="80" required placeholder="Por ejemplo, Otorrino"
                   value="{{ $nuevaConError ? old('nombre') : '' }}"
                   class="form-control @if ($nuevaConError && $errors->has('nombre')) is-invalid @endif">
            @if ($nuevaConError) @error('nombre') <p class="dialogo-error">{{ $message }}</p> @enderror @endif
            <fieldset class="dialogo-grupo">
                <legend>Color</legend>
                <div class="paleta-actividades">
                    @foreach (ColorActividad::cases() as $color)
                        <label class="paleta-opcion {{ $color->clase() }}">
                            <input type="radio" name="color" value="{{ $color->value }}" @checked($colorNueva === $color->value)>
                            <span class="paleta-punto" aria-hidden="true"></span>
                            <span class="paleta-nombre">{{ $color->etiqueta() }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($nuevaConError) @error('color') <p class="dialogo-error">{{ $message }}</p> @enderror @endif
            </fieldset>
            <div class="dialogo-botones">
                <button type="submit" class="btn btn-foco btn-sm"><i class="bi bi-plus-lg" aria-hidden="true"></i> Crear actividad</button>
            </div>
        </form>
    </div>
</dialog>
