<?php

namespace App\Http\Controllers;

use App\Enums\ZonaSemana;
use App\Http\Requests\FiltroAgendaRequest;
use App\Models\Caja;
use App\Models\Contexto;
use App\Services\Agenda\PlannerSemanal;
use App\Services\Agenda\Semana;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class AgendaController extends Controller
{
    /** Planner semanal: la puerta de entrada de la agenda. */
    public function index(FiltroAgendaRequest $request, PlannerSemanal $planner): View
    {
        return view('agenda.planner', $planner->datos($request->lunes()) + ['zonas' => ZonaSemana::cases()]);
    }

    /** Hoja del día: lienzo en cuadrícula con las cajas de ese día. */
    public function dia(string $fecha): View
    {
        abort_unless(Semana::esFechaValida($fecha), 404);

        $dia = CarbonImmutable::parse($fecha)->startOfDay();

        return view('agenda.dia', [
            'dia' => $dia,
            'esHoy' => $dia->isToday(),
            'lunes' => Semana::lunesDe($dia),
            'urlAnterior' => route('agenda.dia', ['fecha' => $dia->subDay()->toDateString()]),
            'urlSiguiente' => route('agenda.dia', ['fecha' => $dia->addDay()->toDateString()]),
            'cajas' => Caja::query()->with('actividad:id,nombre,color')->delDia($dia)->enOrdenDeLectura()->get(),
            'actividades' => Contexto::query()->actividades()->get(),
        ]);
    }
}
