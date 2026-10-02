<?php

namespace App\Http\Controllers;

use App\Enums\ZonaSemana;
use App\Http\Requests\FiltroAgendaRequest;
use App\Http\Requests\LayoutPlannerRequest;
use App\Http\Requests\TituloPlannerRequest;
use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\Contexto;
use App\Support\Aviso;
use App\Services\Agenda\PlannerLayout;
use App\Services\Agenda\PlannerSemanal;
use App\Services\Agenda\Semana;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AgendaController extends Controller
{
    /** Planner semanal: la puerta de entrada de la agenda. */
    public function index(FiltroAgendaRequest $request, PlannerSemanal $planner): View
    {
        return view('agenda.planner', $planner->datos($request->lunes()) + ['zonas' => ZonaSemana::cases()]);
    }

    /** Guarda el título del planner; vacío vuelve al valor por defecto. */
    public function titulo(TituloPlannerRequest $request): JsonResponse
    {
        Ajuste::guardar(Ajuste::TITULO_PLANNER, $request->titulo() === '' ? null : $request->titulo());

        return response()->json(['titulo' => Ajuste::tituloPlanner()]);
    }

    /** Guarda de una vez la posición y el tamaño de las tarjetas del planner, solo para esa semana. */
    public function layout(LayoutPlannerRequest $request, string $semana): JsonResponse
    {
        abort_unless(Semana::esFechaValida($semana), 404);

        PlannerLayout::guardar($semana, $request->tarjetas());

        return response()->json(['ok' => true]);
    }

    /** Borra la disposición propia de esa semana: vuelve a la de fábrica. */
    public function restablecerLayout(string $semana): RedirectResponse
    {
        abort_unless(Semana::esFechaValida($semana), 404);

        PlannerLayout::restablecer($semana);

        return redirect()->route('agenda.index', ['semana' => $semana])->with(Aviso::flash('Disposición restablecida para esta semana.'));
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
