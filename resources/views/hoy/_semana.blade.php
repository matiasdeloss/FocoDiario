{{-- Tira de la semana actual (lunes a domingo). Requiere: $semana (ver EventosCalendario::semanaActual). --}}
@php
    $nombres = ['tarea' => ['tarea', 'tareas'], 'recordatorio' => ['recordatorio', 'recordatorios'], 'nota' => ['nota', 'notas'], 'sesion' => ['sesión', 'sesiones']];
@endphp
<section class="tarjeta p-3 mb-3" aria-labelledby="semana-titulo">
    <h2 class="tarjeta-titulo d-flex justify-content-between" id="semana-titulo">Esta semana <a href="{{ route('calendario.index') }}" class="text-decoration-none text-lowercase fw-normal">ver calendario</a></h2>
    <div class="semana-tira">
        @foreach ($semana as $dia)
            @php
                $partes = collect($nombres)->filter(fn ($n, $tipo) => ($dia['conteos'][$tipo] ?? 0) > 0)
                    ->map(fn ($n, $tipo) => $dia['conteos'][$tipo].' '.$n[$dia['conteos'][$tipo] === 1 ? 0 : 1]);
            @endphp
            <a href="{{ route('calendario.index', ['fecha' => $dia['fecha']->toDateString()]) }}"
               class="semana-dia {{ $dia['hoy'] ? 'semana-dia-hoy' : '' }}"
               @if ($dia['hoy']) aria-current="date" @endif
               aria-label="{{ $dia['fecha']->translatedFormat('l j') }}{{ $partes->isEmpty() ? ': sin actividad' : ': '.$partes->implode(', ') }}">
                <span class="semana-dia-nombre">{{ $dia['fecha']->translatedFormat('D') }}</span>
                <span class="semana-dia-numero">{{ $dia['fecha']->format('j') }}</span>
                <span class="semana-dia-puntos" aria-hidden="true">
                    @foreach ($dia['conteos'] as $tipo => $cantidad)
                        <span class="semana-punto tipo-{{ $tipo }}"><i></i>{{ $cantidad }}</span>
                    @endforeach
                </span>
            </a>
        @endforeach
    </div>
</section>
