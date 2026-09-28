@extends('layouts.app')

@section('titulo', 'Hoy · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Hoy</h1>
        <p class="text-secondary mb-0">{{ now()->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>

    @include('hoy._semana')

    @include('hoy._nota-rapida')

    @include('hoy._recomendaciones')

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Tareas abiertas</div>
                <div class="metrica-valor">{{ $tareasAbiertas }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Horas aprovechadas hoy</div>
                <div class="metrica-valor">{{ $horasAprovechadas }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Recordatorios pendientes</div>
                <div class="metrica-valor">{{ $recordatorios->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 p-md-4 h-100">
                <div class="metrica-etiqueta">Pomodoros hoy</div>
                <div class="metrica-valor">0</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="tarjeta p-4 h-100 text-center">
                <h2 class="tarjeta-titulo">Pomodoro</h2>
                <div class="pomodoro-tiempo mb-2" data-pomodoro-resumen>25:00</div>
                <p class="small text-secondary mb-4" data-pomodoro-resumen-detalle>Foco 25 min, descanso 5 min</p>
                <a href="{{ route('estudio.index') }}" class="btn btn-foco">
                    <i class="bi bi-play-fill"></i> Ir a Estudio
                </a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta p-4 h-100">
                <h2 class="tarjeta-titulo d-flex justify-content-between">Próximas tareas <a href="{{ route('tareas.index') }}" class="text-decoration-none text-lowercase fw-normal">ver todas</a></h2>
                @forelse ($proximasTareas as $tarea)
                    <div class="lista-fila">
                        <span>{{ $tarea->titulo }}</span>
                        <span class="lista-fila-meta">{{ $tarea->fecha_limite?->format('d/m') }}</span>
                    </div>
                @empty
                    <p class="estado-vacio">No tenés tareas abiertas. Cuando cargues una, aparece acá.</p>
                @endforelse
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta p-4 h-100">
                <h2 class="tarjeta-titulo d-flex justify-content-between">Recordatorios <a href="{{ route('recordatorios.index') }}" class="text-decoration-none text-lowercase fw-normal">ver todos</a></h2>
                @forelse ($recordatorios as $recordatorio)
                    <div class="lista-fila">
                        <span>{{ $recordatorio->mensaje }}</span>
                        <span class="lista-fila-meta">{{ $recordatorio->recordar_en->format('d/m H:i') }}</span>
                    </div>
                @empty
                    <p class="estado-vacio">Sin recordatorios pendientes.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
