@extends('layouts.app')

@section('titulo', 'Hoy · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 mb-1">Hoy</h1>
        <p class="text-secondary mb-0">{{ now()->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 h-100">
                <div class="text-secondary small">Tareas abiertas</div>
                <div class="metrica-valor">{{ $tareasAbiertas }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 h-100">
                <div class="text-secondary small">Horas aprovechadas hoy</div>
                <div class="metrica-valor">{{ $horasAprovechadas }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 h-100">
                <div class="text-secondary small">Recordatorios pendientes</div>
                <div class="metrica-valor">{{ $recordatorios->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta p-3 h-100">
                <div class="text-secondary small">Pomodoros hoy</div>
                <div class="metrica-valor">0</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="tarjeta p-4 h-100 text-center">
                <h2 class="h6 text-secondary text-uppercase mb-3">Pomodoro</h2>
                <div class="pomodoro-tiempo mb-3">25:00</div>
                <button type="button" class="btn btn-foco" disabled>
                    <i class="bi bi-play-fill"></i> Iniciar
                </button>
                <p class="small text-secondary mt-3 mb-0">Disponible en el próximo paso.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta p-4 h-100">
                <h2 class="h6 text-secondary text-uppercase mb-3">Próximas tareas</h2>
                @forelse ($proximasTareas as $tarea)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $tarea->titulo }}</span>
                        <span class="small text-secondary">{{ $tarea->fecha_limite?->format('d/m') }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No tenés tareas abiertas. Cuando cargues una, aparece acá.</p>
                @endforelse
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta p-4 h-100">
                <h2 class="h6 text-secondary text-uppercase mb-3">Recordatorios</h2>
                @forelse ($recordatorios as $recordatorio)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $recordatorio->mensaje }}</span>
                        <span class="small text-secondary">{{ $recordatorio->recordar_en->format('d/m H:i') }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Sin recordatorios pendientes.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
