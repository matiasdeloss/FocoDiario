<?php

namespace App\Services\Recomendaciones;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Enums\TipoCategoria;
use App\Models\BloqueTiempo;
use App\Models\Recordatorio;
use App\Models\Tarea;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Datos del día ya calculados, para que las reglas no consulten la base. */
final readonly class ContextoRecomendacion
{
    /**
     * @param  Collection<int, BloqueTiempo>  $bloquesHoy  Bloques de hoy ya empezados, con su categoría.
     * @param  ?int  $minutoArranqueHabitual  Mediana (minutos desde medianoche) del primer bloque de cada día, últimos 7 días.
     * @param  Collection<int, Recordatorio>  $recordatoriosAtrasados
     * @param  Collection<int, Recordatorio>  $recordatoriosProximos  Los de la próxima hora.
     */
    public function __construct(
        public CarbonImmutable $ahora,
        public Collection $bloquesHoy,
        public int $minutosProductivos,
        public int $minutosOcio,
        public int $minutosDescanso,
        public int $minutosSeguidosSinPausa,
        public ?int $minutoArranqueHabitual,
        public int $tareasAbiertas,
        public int $tareasVencidas,
        public bool $hayTareaAltaParaHoy,
        public int $tareasCompletadasHoy,
        public Collection $recordatoriosAtrasados,
        public Collection $recordatoriosProximos,
        public bool $hizoEjercicioHoy,
    ) {}

    public static function desdeBaseDeDatos(CarbonImmutable $ahora): self
    {
        $bloquesHoy = BloqueTiempo::with('categoria')
            ->whereBetween('inicio', [$ahora->startOfDay(), $ahora])
            ->orderBy('inicio')
            ->get();

        $minutos = ['productiva' => 0, 'ocio' => 0, 'descanso' => 0];
        foreach ($bloquesHoy as $bloque) {
            $tipo = $bloque->categoria->tipo;
            if ($tipo instanceof TipoCategoria) {
                $minutos[$tipo->value] += self::minutosHasta($bloque, $ahora);
            }
        }

        $hoy = $ahora->toDateString();

        return new self(
            ahora: $ahora,
            bloquesHoy: $bloquesHoy,
            minutosProductivos: $minutos['productiva'],
            minutosOcio: $minutos['ocio'],
            minutosDescanso: $minutos['descanso'],
            minutosSeguidosSinPausa: self::minutosSeguidos($bloquesHoy, $ahora),
            minutoArranqueHabitual: self::arranqueHabitual($ahora),
            tareasAbiertas: Tarea::abiertas()->count(),
            tareasVencidas: Tarea::abiertas()->whereDate('fecha_limite', '<', $hoy)->count(),
            hayTareaAltaParaHoy: Tarea::abiertas()
                ->where('prioridad', PrioridadTarea::Alta)
                ->where(fn ($consulta) => $consulta->whereNull('fecha_limite')->orWhereDate('fecha_limite', '<=', $hoy))
                ->exists(),
            tareasCompletadasHoy: Tarea::where('estado', EstadoTarea::Completada)->whereDate('updated_at', $hoy)->count(),
            recordatoriosAtrasados: Recordatorio::pendientes()->where('recordar_en', '<', $ahora)->orderBy('recordar_en')->limit(5)->get(),
            recordatoriosProximos: Recordatorio::pendientes()->whereBetween('recordar_en', [$ahora, $ahora->addHour()])->orderBy('recordar_en')->limit(5)->get(),
            hizoEjercicioHoy: $bloquesHoy->contains(
                fn (BloqueTiempo $bloque) => str_contains(mb_strtolower($bloque->categoria->nombre), 'ejercicio'),
            ),
        );
    }

    public function minutoDelDia(): int
    {
        return $this->ahora->hour * 60 + $this->ahora->minute;
    }

    /** Entre las 06:00 y las 21:00. */
    public function esDeDia(): bool
    {
        return $this->minutoDelDia() >= 6 * 60 && $this->minutoDelDia() < 21 * 60;
    }

    private static function minutosHasta(BloqueTiempo $bloque, CarbonImmutable $ahora): int
    {
        $fin = $bloque->fin->greaterThan($ahora) ? $ahora : $bloque->fin;

        return max(0, intdiv($fin->getTimestamp() - $bloque->inicio->getTimestamp(), 60));
    }

    /**
     * Minutos del tramo productivo más reciente sin pausa (huecos de hasta 5 min).
     * Es 0 si el último bloque no es productivo o terminó hace más de 10 min.
     *
     * @param  Collection<int, BloqueTiempo>  $bloques  Ordenados por inicio.
     */
    private static function minutosSeguidos(Collection $bloques, CarbonImmutable $ahora): int
    {
        $ultimo = $bloques->last();
        if (! $ultimo || $ultimo->categoria->tipo !== TipoCategoria::Productiva) {
            return 0;
        }
        if ($ahora->getTimestamp() - $ultimo->fin->getTimestamp() > 10 * 60) {
            return 0;
        }

        $fin = $ultimo->fin->greaterThan($ahora) ? $ahora : $ultimo->fin;
        $inicio = $ultimo->inicio;

        foreach ($bloques->slice(0, -1)->reverse() as $anterior) {
            $esProductivo = $anterior->categoria->tipo === TipoCategoria::Productiva;
            $hueco = $inicio->getTimestamp() - $anterior->fin->getTimestamp();
            if (! $esProductivo || $hueco > 5 * 60) {
                break;
            }
            $inicio = $anterior->inicio;
        }

        return intdiv($fin->getTimestamp() - $inicio->getTimestamp(), 60);
    }

    /** Mediana de la hora del primer bloque de cada uno de los últimos 7 días (incluye hoy). */
    private static function arranqueHabitual(CarbonImmutable $ahora): ?int
    {
        $minutos = BloqueTiempo::query()
            ->whereBetween('inicio', [$ahora->subDays(6)->startOfDay(), $ahora])
            ->orderBy('inicio')
            ->pluck('inicio')
            ->groupBy(fn ($inicio) => $inicio->toDateString())
            ->map(fn ($delDia) => $delDia->first()->hour * 60 + $delDia->first()->minute)
            ->sort()
            ->values();

        if ($minutos->isEmpty()) {
            return null;
        }

        $mitad = intdiv($minutos->count(), 2);

        return $minutos->count() % 2 === 1
            ? $minutos[$mitad]
            : intdiv($minutos[$mitad - 1] + $minutos[$mitad], 2);
    }
}
