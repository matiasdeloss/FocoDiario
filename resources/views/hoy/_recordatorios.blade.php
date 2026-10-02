{{-- Recordatorios pendientes con fecha. Requiere: $recordatorios, $recordatoriosSinFecha --}}
<section id="hoy-recordatorios" class="hoy-tarjeta hoy-lateral" aria-labelledby="hoy-recordatorios-titulo" @if (! empty($oob)) hx-swap-oob="true" @endif data-recordatorios>
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="hoy-recordatorios-titulo">Recordatorios</h2>
        <a href="{{ route('tareas.index', ['tipo' => 'recordatorio']) }}" class="hoy-enlace">ver todos</a>
    </div>

    <ul class="hoy-lista" role="list">
        @foreach ($recordatorios as $recordatorio)
            @php $atrasado = $recordatorio->recordar_en->isPast(); @endphp
            <li class="hoy-item" data-recordatorio="{{ $recordatorio->id }}">
                <button type="button" class="hoy-fila" role="checkbox" aria-checked="false"
                        data-url-avisar="{{ route('recordatorios.avisar', $recordatorio) }}"
                        data-url-reactivar="{{ route('recordatorios.reactivar', $recordatorio) }}">
                    <span class="hoy-check" aria-hidden="true">@include('hoy._icono-check')</span>
                    <span class="hoy-fila-texto">
                        <span class="hoy-fila-titulo">{{ $recordatorio->mensaje !== '' ? $recordatorio->mensaje : 'Sin título' }}</span>
                        <span @class(['hoy-fila-cuando', 'es-atrasado' => $atrasado])>
                            @if ($atrasado)<span class="hoy-solo-lector">Atrasado: </span>@endif{{ \App\Support\CuandoCorto::para($recordatorio->recordar_en) }}
                        </span>
                    </span>
                </button>
            </li>
        @endforeach
    </ul>

    @if ($recordatorios->isEmpty())
        <p class="hoy-vacio">Sin recordatorios pendientes con fecha.</p>
    @endif

    @if ($recordatoriosSinFecha > 0)
        <p class="hoy-aviso-suave">
            {{ $recordatoriosSinFecha }} sin fecha:
            <a href="{{ route('calendario.index') }}">ubicarlos en el calendario</a>
        </p>
    @endif
</section>
