{{-- Caja Notas o Pendiente de la semana (una columna de la caja grande de abajo). Requiere: $zona (ZonaSemana), $caja (Caja o null), $lunes --}}
@php
    use App\Enums\ZonaSemana;

    // Notas nace como texto libre y Pendiente como lista con casillas, como en el cuaderno.
    $tipo = $caja?->tipo->value ?? ($zona === ZonaSemana::Pendiente ? 'lista' : 'texto');
    $items = $caja?->itemsLista() ?? [];

    if ($tipo === 'lista' && $items === []) {
        $items = [['texto' => '', 'hecho' => false]];
    }

    $estado = ['tipo' => $tipo, 'contenido' => $caja?->contenido, 'items' => $items];
    $etiqueta = $zona->etiqueta().' de la semana';
@endphp
<article class="caja plan-nota" data-caja data-metodo="PUT" data-url="{{ route('agenda.semana.guardar', [$lunes->toDateString(), $zona->value]) }}"
         data-estado="{{ json_encode($estado, JSON_UNESCAPED_UNICODE) }}" data-etiqueta-contenido="{{ $etiqueta }}"
         aria-labelledby="plan-nota-{{ $zona->value }}">
    <header class="plan-nota-cab">
        <h2 class="plan-nota-titulo" id="plan-nota-{{ $zona->value }}">{{ $zona->etiqueta() }}</h2>
        <div class="plan-tipo" role="group" aria-label="Formato de {{ mb_strtolower($zona->etiqueta()) }}">
            <button type="button" data-tipo="texto" aria-pressed="{{ $tipo === 'texto' ? 'true' : 'false' }}">Texto</button>
            <button type="button" data-tipo="lista" aria-pressed="{{ $tipo === 'lista' ? 'true' : 'false' }}">Lista</button>
        </div>
    </header>
    @include('agenda._hoja', ['tipo' => $tipo, 'contenido' => $caja?->contenido, 'items' => $items, 'etiqueta' => $etiqueta])
</article>
