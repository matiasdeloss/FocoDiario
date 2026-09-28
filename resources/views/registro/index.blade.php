@extends('layouts.app')

@section('titulo', 'Registro · FocoDiario')

@php
    use App\Support\Duracion;

    $fechaTexto = $fecha->format('Y-m-d');
    $esHoy = $fecha->isToday();
    $bloqueNuevo = new \App\Models\BloqueTiempo;
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Registro del día</h1>
            <p class="text-secondary mb-0">{{ $fecha->translatedFormat('l j \d\e F \d\e Y') }}{{ $esHoy ? ' (hoy)' : '' }}</p>
        </div>
        <form method="GET" action="{{ route('registro.index') }}" class="d-flex align-items-end gap-2">
            <a href="{{ route('registro.index', ['fecha' => $fecha->subDay()->format('Y-m-d')]) }}" class="btn-icono" title="Día anterior" aria-label="Día anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div>
                <label for="fecha-selector" class="form-label">Fecha</label>
                <input type="date" id="fecha-selector" name="fecha" value="{{ $fechaTexto }}" class="form-control" onchange="this.form.submit()">
            </div>
            <a href="{{ route('registro.index', ['fecha' => $fecha->addDay()->format('Y-m-d')]) }}" class="btn-icono" title="Día siguiente" aria-label="Día siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
            <noscript><button type="submit" class="btn btn-foco-suave">Ir</button></noscript>
            @unless ($esHoy)
                <a href="{{ route('registro.index') }}" class="btn btn-foco-suave">Hoy</a>
            @endunless
        </form>
    </div>

    <div class="tarjeta p-4 mb-3">
        <h2 class="tarjeta-titulo">Nuevo bloque de tiempo</h2>
        @include('registro._formulario', [
            'accion' => route('registro.store'),
            'metodo' => 'POST',
            'bloque' => $bloqueNuevo,
            'fecha' => $fechaTexto,
            'textoBoton' => 'Agregar bloque',
        ])
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Total registrado</div>
                <div class="metrica-valor">{{ Duracion::formatear($resumen['total']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Productivas</div>
                <div class="metrica-valor">{{ Duracion::formatear($resumen['productiva']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Ocio</div>
                <div class="metrica-valor">{{ Duracion::formatear($resumen['ocio']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Descanso</div>
                <div class="metrica-valor">{{ Duracion::formatear($resumen['descanso']) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="tarjeta h-100">
                <h2 class="tarjeta-titulo px-4 pt-4 mb-3">Bloques del día</h2>
                @if ($bloques->isEmpty())
                    <p class="estado-vacio px-4 pb-3">No hay bloques registrados en este día.</p>
                @else
                    <div class="tabla-foco-contenedor">
                        <table class="table tabla-foco">
                            <thead>
                                <tr>
                                    <th scope="col">Horario</th>
                                    <th scope="col">Duración</th>
                                    <th scope="col">Categoría</th>
                                    <th scope="col">Foco</th>
                                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bloques as $bloque)
                                    <tr>
                                        <td class="text-nowrap">{{ $bloque->inicio->format('H:i') }} - {{ $bloque->fin->format('H:i') }}</td>
                                        <td class="text-nowrap">{{ Duracion::formatear($bloque->duracionEnMinutos()) }}</td>
                                        <td>
                                            <div>{{ $bloque->categoria->nombre }}</div>
                                            <div class="small text-secondary">
                                                {{ $bloque->categoria->tipo->etiqueta() }}@if ($bloque->tarea) · {{ $bloque->tarea->titulo }}@endif
                                            </div>
                                        </td>
                                        <td>{{ $bloque->concentracion ?? '-' }}</td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('registro.edit', $bloque) }}" class="btn-icono" title="Editar" aria-label="Editar bloque de las {{ $bloque->inicio->format('H:i') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('registro.destroy', $bloque) }}" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar este bloque?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar bloque de las {{ $bloque->inicio->format('H:i') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="tarjeta p-4 h-100">
                <h2 class="tarjeta-titulo">Datos del día</h2>
                <form method="POST" action="{{ route('registro.dia') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="fecha" value="{{ $fechaTexto }}">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="desperto_a" class="form-label">Me desperté</label>
                            <input type="time" id="desperto_a" name="desperto_a"
                                   value="{{ old('desperto_a', $dia?->desperto_a?->format('H:i')) }}"
                                   class="form-control @error('desperto_a') is-invalid @enderror">
                            @error('desperto_a') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label for="durmio_a" class="form-label">Me dormí</label>
                            <input type="time" id="durmio_a" name="durmio_a"
                                   value="{{ old('durmio_a', $dia?->durmio_a?->format('H:i')) }}"
                                   class="form-control @error('durmio_a') is-invalid @enderror">
                            @error('durmio_a') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-foco-suave">Guardar datos</button>
                </form>

                <div class="lista-fila mt-4">
                    <span>Horas despierto</span>
                    <span class="lista-fila-meta">
                        @if ($dia?->minutosDespierto() !== null)
                            {{ Duracion::formatear($dia->minutosDespierto()) }}
                        @else
                            Falta un dato
                        @endif
                    </span>
                </div>
                @if ($dia?->durmio_a && ! $dia->durmio_a->isSameDay($fecha))
                    <p class="small text-secondary mb-0">La hora de dormir cae después de medianoche.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
