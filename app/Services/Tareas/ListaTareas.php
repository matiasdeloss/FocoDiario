<?php

namespace App\Services\Tareas;

use App\Enums\EstadoTarea;
use App\Models\Contexto;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Support\Busqueda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Arma la lista unificada de Tareas y Recordatorios: filtra, mezcla, agrupa por tiempo y ordena.
 * Los recordatorios no tienen contexto ni prioridad: si se filtra por alguno de los dos, no aparecen.
 */
class ListaTareas
{
    public const GRUPOS = [
        'vencidas' => 'Vencidas',
        'hoy' => 'Hoy',
        'manana' => 'Mañana',
        'semana' => 'Esta semana',
        'despues' => 'Más adelante',
        'sin_fecha' => 'Sin fecha',
    ];

    /** Cuántas completadas se muestran (las más recientes). */
    private const COMPLETADAS = 30;

    /**
     * @param  array{tipo?: string|null, estado?: string|null, prioridad?: string|null, contexto?: int|string|null, q?: string|null, contexto_ids?: list<int>}  $filtros
     * @return array{grupos: array<string, Collection<int, ItemLista>>, completadas: Collection<int, ItemLista>, hechasTotal: int}
     */
    public function armar(array $filtros): array
    {
        $tipo = $filtros['tipo'] ?? 'todo';
        $estado = $filtros['estado'] ?? 'abiertas';
        $hoy = today();

        $conTareas = $tipo !== 'recordatorio';
        // El contexto filtra también por sus descendientes (como Notas); se resuelve una sola vez para las consultas de abajo.
        if (! empty($filtros['contexto'])) {
            $filtros['contexto_ids'] = Contexto::find($filtros['contexto'])?->idsConDescendientes() ?? [(int) $filtros['contexto']];
        }

        $conRecordatorios = $tipo !== 'tarea' && empty($filtros['prioridad']) && empty($filtros['contexto']);

        $abiertos = collect();
        $completadas = collect();
        $hechasTotal = 0;

        if ($estado !== 'hechas') {
            if ($conTareas) {
                $this->tareas($filtros)->abiertas()->get()->each(fn (Tarea $t) => $abiertos->push(ItemLista::deTarea($t)));
            }

            if ($conRecordatorios) {
                $this->recordatorios($filtros)->pendientes()->with('tarea')->get()
                    ->each(fn (Recordatorio $r) => $abiertos->push(ItemLista::deRecordatorio($r)));
            }
        }

        if ($conTareas) {
            $consulta = $this->tareas($filtros)->where('estado', EstadoTarea::Completada);
            $hechasTotal += $consulta->count();
            $consulta->orderByDesc('updated_at')->orderByDesc('id')->limit(self::COMPLETADAS)->get()
                ->each(fn (Tarea $t) => $completadas->push([$t->updated_at, ItemLista::deTarea($t)]));
        }

        if ($conRecordatorios) {
            $consulta = $this->recordatorios($filtros)->whereNotNull('avisado_en');
            $hechasTotal += $consulta->count();
            $consulta->with('tarea')->orderByDesc('avisado_en')->limit(self::COMPLETADAS)->get()
                ->each(fn (Recordatorio $r) => $completadas->push([$r->avisado_en, ItemLista::deRecordatorio($r)]));
        }

        $completadas = $completadas->sortByDesc(fn ($par) => $par[0]?->getTimestamp() ?? 0)
            ->take(self::COMPLETADAS)->map(fn ($par) => $par[1])->values();

        $grupos = collect(self::GRUPOS)->map(fn () => collect())->all();

        foreach ($abiertos as $item) {
            $grupos[$item->grupo($hoy)]->push($item);
        }

        foreach ($grupos as $clave => $items) {
            $grupos[$clave] = $items->sort(fn (ItemLista $a, ItemLista $b) => $this->comparar($a, $b))->values();
        }

        return ['grupos' => $grupos, 'completadas' => $completadas, 'hechasTotal' => $hechasTotal];
    }

    /** Conteos del encabezado, sin filtros: abiertas por tipo y vencidas. */
    public function resumen(): array
    {
        $hoy = today();

        return [
            'tareas' => Tarea::abiertas()->count(),
            'recordatorios' => Recordatorio::pendientes()->count(),
            'vencidas' => Tarea::abiertas()->whereDate('fecha_limite', '<', $hoy)->count()
                + Recordatorio::pendientes()->whereDate('recordar_en', '<', $hoy)->count(),
        ];
    }

    private function tareas(array $filtros): Builder
    {
        return Tarea::query()
            ->with(['contexto', 'notas'])
            ->when($filtros['prioridad'] ?? null, fn ($c, $prioridad) => $c->where('prioridad', $prioridad))
            ->when($filtros['contexto'] ?? null, fn ($c, $contexto) => $c->whereIn('contexto_id', $filtros['contexto_ids'] ?? [(int) $contexto]))
            ->when($filtros['q'] ?? null, function ($c, $q) {
                $patron = Busqueda::patron($q);

                $c->where(fn ($w) => $w->whereRaw(Busqueda::condicion('tareas.titulo'), [$patron])
                    ->orWhereRaw(Busqueda::condicion('tareas.descripcion'), [$patron])
                    ->orWhereHas('contexto', fn ($contexto) => $contexto->whereRaw(Busqueda::condicion('contextos.nombre'), [$patron])));
            });
    }

    private function recordatorios(array $filtros): Builder
    {
        return Recordatorio::query()
            ->when($filtros['q'] ?? null, function ($c, $q) {
                $patron = Busqueda::patron($q);

                $c->where(fn ($w) => $w->whereRaw(Busqueda::condicion('mensaje'), [$patron])->orWhereRaw(Busqueda::condicion('descripcion'), [$patron]));
            });
    }

    /** Por momento (los sin hora, antes que los recordatorios del mismo día), luego prioridad y, al final, lo más nuevo. */
    private function comparar(ItemLista $a, ItemLista $b): int
    {
        $ta = $a->cuando?->getTimestamp() ?? PHP_INT_MAX;
        $tb = $b->cuando?->getTimestamp() ?? PHP_INT_MAX;

        return [$ta, $a->pesoPrioridad(), -$a->id()] <=> [$tb, $b->pesoPrioridad(), -$b->id()];
    }
}
