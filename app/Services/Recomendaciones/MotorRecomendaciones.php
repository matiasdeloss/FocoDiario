<?php

namespace App\Services\Recomendaciones;

use App\Enums\CategoriaRecomendacion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MotorRecomendaciones
{
    /** Categoría de cada regla, para poder filtrar las recomendaciones en pantalla. */
    private const CATEGORIAS = [
        Reglas\SuenoRecomendado::class => CategoriaRecomendacion::Salud,
        Reglas\Descansos::class => CategoriaRecomendacion::Salud,
        Reglas\Ejercicio::class => CategoriaRecomendacion::Salud,
        Reglas\Siesta::class => CategoriaRecomendacion::Salud,
        Reglas\Balance::class => CategoriaRecomendacion::Bienestar,
        Reglas\Logros::class => CategoriaRecomendacion::Bienestar,
        Reglas\OcioComoRecompensa::class => CategoriaRecomendacion::Bienestar,
        Reglas\RepasoEspaciado::class => CategoriaRecomendacion::Estudio,
        Reglas\ParcialCercano::class => CategoriaRecomendacion::Estudio,
        Reglas\PomodorosSemana::class => CategoriaRecomendacion::Estudio,
    ];

    /** @var list<Regla> */
    private array $reglas;

    /** @param  list<Regla>|null  $reglas  Por defecto, todas las reglas del sistema. */
    public function __construct(?array $reglas = null)
    {
        $this->reglas = $reglas ?? self::reglasPorDefecto();
    }

    /**
     * Corre todas las reglas y devuelve las recomendaciones de mayor a menor prioridad.
     *
     * @param  ?CarbonInterface  $ahora  Hora de referencia; por defecto, el momento actual.
     * @return Collection<int, Recomendacion>
     */
    public function generar(?CarbonInterface $ahora = null): Collection
    {
        $contexto = ContextoRecomendacion::desdeBaseDeDatos(
            CarbonImmutable::instance($ahora ?? now()),
        );

        return collect($this->reglas)
            ->flatMap(fn (Regla $regla) => collect($regla->evaluar($contexto))->map(fn (Recomendacion $r) => $r->categoria ? $r : new Recomendacion(
                $r->titulo, $r->mensaje, $r->tipo, $r->icono, $r->prioridad, $r->fuente, $r->datos,
                self::CATEGORIAS[$regla::class] ?? CategoriaRecomendacion::Productividad,
            )))
            ->sortByDesc(fn (Recomendacion $recomendacion) => $recomendacion->prioridad)
            ->values();
    }

    /** @return list<Regla> */
    public static function reglasPorDefecto(): array
    {
        return [
            new Reglas\SuenoRecomendado,
            new Reglas\FocoDelDia,
            new Reglas\Balance,
            new Reglas\SinActividad,
            new Reglas\Tareas,
            new Reglas\Recordatorios,
            new Reglas\MomentoDelDia,
            new Reglas\Descansos,
            new Reglas\Logros,
            new Reglas\PlanificarManana,
            new Reglas\RevisionSemanal,
            new Reglas\Ejercicio,
            new Reglas\OcioComoRecompensa,
            new Reglas\RepasoEspaciado,
            new Reglas\IntencionDeImplementacion,
            new Reglas\Siesta,
            new Reglas\ParcialCercano,
            new Reglas\PomodorosSemana,
        ];
    }
}
