{{-- Esta semana: siete casilleros seleccionables. Requiere: $semana (ver App\Services\Hoy\SemanaHoy). Los datos viajan en data-semana y el JS cambia de día sin recargar. --}}
@php
    $hoy = collect($semana)->firstWhere('hoy', true) ?? $semana[0];
    $tiposEvento = ['tarea' => 'Tarea', 'recordatorio' => 'Recordatorio', 'nota' => 'Nota', 'sesion' => 'Estudio', 'planner' => 'Planner'];
@endphp
<section id="hoy-semana" class="hoy-tarjeta hoy-semana" aria-labelledby="hoy-semana-titulo"
         @if (! empty($oob)) hx-swap-oob="true" @endif
         data-semana="{{ json_encode($semana, JSON_UNESCAPED_UNICODE) }}" data-url-calendario="{{ route('calendario.index') }}">
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="hoy-semana-titulo">Esta semana</h2>
        <span class="hoy-meta-grupo">
            <span class="hoy-meta">{{ ucfirst(\Illuminate\Support\Carbon::parse($hoy['fecha'])->translatedFormat('F Y')) }}</span>
            <a href="{{ route('agenda.index') }}" class="hoy-enlace">ver agenda</a>
            <a href="{{ route('calendario.index') }}" class="hoy-enlace">ver calendario</a>
        </span>
    </div>

    <div class="hoy-dias" role="group" aria-label="Días de esta semana">
        @foreach ($semana as $dia)
            @php
                $puntos = $dia['puntos'];
                $visibles = array_slice($puntos, 0, \App\Services\Hoy\SemanaHoy::MAX_PUNTOS);
                $extra = count($puntos) - count($visibles);
            @endphp
            <button type="button" class="hoy-dia" data-fecha="{{ $dia['fecha'] }}" @if ($dia['hoy']) data-hoy @endif aria-pressed="{{ $dia['hoy'] ? 'true' : 'false' }}"
                    aria-label="{{ $dia['largo'] }}{{ $dia['hoy'] ? ', hoy' : '' }}: {{ $dia['resumen'] !== '' ? $dia['resumen'] : 'sin eventos' }}">
                <span class="hoy-dia-nombre" aria-hidden="true">
                    <span class="hoy-dia-nombre-completo">{{ $dia['completo'] }}</span>
                    <span class="hoy-dia-nombre-corto">{{ $dia['corto'] }}</span>
                    <span class="hoy-dia-nombre-min">{{ $dia['nombre'] }}</span>
                </span>
                <span class="hoy-dia-celda" aria-hidden="true">
                    <span class="hoy-dia-numero">{{ $dia['numero'] }}</span>
                    <span class="hoy-dia-puntos">
                        @foreach ($visibles as $punto)
                            <span @class(['hoy-dia-punto', 'tipo-'.$punto['tipo'], 'es-hecho' => $punto['hecho']])></span>
                        @endforeach
                        @if ($extra > 0)
                            <span class="hoy-dia-mas">+{{ $extra }}</span>
                        @endif
                    </span>
                </span>
            </button>
        @endforeach
    </div>

    <div class="hoy-eventos" data-eventos aria-live="polite">
        @forelse ($hoy['eventos'] as $evento)
            <a class="hoy-evento" href="{{ route('calendario.index', ['fecha' => $evento['fecha']]) }}">
                <span class="hoy-evento-titulo">
                    <span class="hoy-punto tipo-{{ $evento['tipo'] }}" aria-hidden="true"></span>
                    <span class="hoy-solo-lector">{{ $tiposEvento[$evento['tipo']] ?? '' }}: </span>
                    <span @class(['hoy-tachado' => $evento['hecho']])>{{ $evento['titulo'] }}</span>
                </span>
                <span class="hoy-evento-hora">{{ $evento['hora'] ?? $evento['detalle'] }}</span>
            </a>
        @empty
            <p class="hoy-vacio">Nada agendado este día.</p>
        @endforelse
    </div>
</section>
