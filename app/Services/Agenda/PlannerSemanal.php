<?php

namespace App\Services\Agenda;

use App\Enums\ZonaSemana;
use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\Contexto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Datos del planner semanal: los siete días con sus cajas por hora, las cajas de la semana y las actividades. */
class PlannerSemanal
{
    private const CLAVES_DIA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /**
     * @return array{
     *     tituloPlanner: string, lunes: CarbonImmutable, domingo: CarbonImmutable, dias: list<array<string, mixed>>,
     *     semanales: array<string, Caja|null>, actividades: Collection<int, Contexto>,
     *     etiquetaSemana: string, etiquetaMes: string, esEstaSemana: bool,
     *     urlAnterior: string, urlSiguiente: string
     * }
     */
    public function datos(CarbonImmutable $lunes): array
    {
        $domingo = $lunes->addDays(6);
        $hoy = CarbonImmutable::today();

        $porDia = Caja::query()
            ->with('actividad:id,nombre,color')
            ->entreFechas($lunes, $domingo)
            ->enOrdenDePlanner()
            ->get()
            ->groupBy(fn (Caja $caja) => $caja->fecha->toDateString());

        $semanales = Caja::query()->deLaSemana($lunes)->get()->keyBy(fn (Caja $caja) => $caja->zona?->value);

        $dias = [];
        foreach (self::CLAVES_DIA as $indice => $clave) {
            $fecha = $lunes->addDays($indice);
            $dias[] = [
                'clave' => $clave,
                'fecha' => $fecha,
                'nombre' => ucfirst($fecha->translatedFormat('l')),
                'numero' => $fecha->day,
                'esHoy' => $fecha->isSameDay($hoy),
                'cajas' => $porDia->get($fecha->toDateString(), collect()),
            ];
        }

        return [
            'tituloPlanner' => Ajuste::tituloPlanner(),
            'lunes' => $lunes,
            'domingo' => $domingo,
            'dias' => $dias,
            'semanales' => [
                ZonaSemana::Notas->value => $semanales->get(ZonaSemana::Notas->value),
                ZonaSemana::Pendiente->value => $semanales->get(ZonaSemana::Pendiente->value),
            ],
            'actividades' => Contexto::query()->actividades()->get(),
            'etiquetaSemana' => $this->etiquetaSemana($lunes, $domingo),
            'etiquetaMes' => $this->etiquetaMes($lunes, $domingo),
            'esEstaSemana' => $lunes->isSameDay(Semana::lunesDe($hoy)),
            'urlAnterior' => route('agenda.index', ['semana' => $lunes->subWeek()->toDateString()]),
            'urlSiguiente' => route('agenda.index', ['semana' => $lunes->addWeek()->toDateString()]),
        ];
    }

    /** "28 sep – 4 oct 2026" (o "28 – 4 oct" si los dos días son del mismo mes). */
    private function etiquetaSemana(CarbonImmutable $lunes, CarbonImmutable $domingo): string
    {
        $inicio = $lunes->month === $domingo->month ? (string) $lunes->day : $lunes->day.' '.$this->mesCorto($lunes);

        return $inicio.' – '.$domingo->day.' '.$this->mesCorto($domingo).' '.$domingo->year;
    }

    /** Mes abreviado sin el punto final ("sep", "oct"). */
    private function mesCorto(CarbonImmutable $fecha): string
    {
        return rtrim($fecha->translatedFormat('M'), '.');
    }

    /** "Septiembre 2026" o, si la semana cruza de mes, "Septiembre – Octubre 2026". */
    private function etiquetaMes(CarbonImmutable $lunes, CarbonImmutable $domingo): string
    {
        if ($lunes->month === $domingo->month) {
            return ucfirst($lunes->translatedFormat('F')).' '.$lunes->year;
        }

        return ucfirst($lunes->translatedFormat('F')).' – '.ucfirst($domingo->translatedFormat('F')).' '.$domingo->year;
    }
}
