<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroNotasRequest;
use App\Http\Requests\MoverNotaRequest;
use App\Http\Requests\NotaRequest;
use App\Enums\ColorActividad;
use App\Models\Contexto;
use App\Models\Nota;
use App\Support\Aviso;
use App\Support\Busqueda;
use App\Support\ColoresDeContexto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Response;

class NotaController extends Controller
{
    public function index(FiltroNotasRequest $request): View
    {
        $datos = $request->validated();
        $filtro = $datos['contexto'] ?? null;
        $busqueda = trim((string) ($datos['q'] ?? ''));
        $color = isset($datos['color']) ? ColorActividad::tryFrom($datos['color']) : null;
        $soloFijadas = (bool) ($datos['fijadas'] ?? false);
        $soloOcultas = (bool) ($datos['ocultas'] ?? false);
        $contextoFiltro = null;

        $notas = Nota::query()->with(['contexto', 'columna'])->ordenadas();


        if ($busqueda !== '') {
            $patron = Busqueda::patron($busqueda);
            $notas->where(fn ($q) => $q->whereRaw(Busqueda::condicion('titulo'), [$patron])->orWhereRaw(Busqueda::condicion('contenido'), [$patron]));
        }

        if ($color) {
            $notas->where('color', $color->value);
        }

        if ($soloFijadas) {
            $notas->where('fijada', true);
        }

        if ($filtro === 'bandeja') {
            $notas->sinContexto();
        } elseif ($filtro !== null) {
            $contextoFiltro = Contexto::find($filtro);

            if ($contextoFiltro) {
                $notas->delContexto($contextoFiltro);
            } else {
                $filtro = null;
            }
        }

        // Ocultas que quedan fuera del listado con el mismo contexto y los mismos filtros (color, texto, fijadas), para el aviso "y N ocultas · ver".
        $ocultasDelFiltro = $filtro !== null && ! $soloOcultas ? (clone $notas)->reorder()->soloOcultas()->count() : 0;

        // La pantalla Notas no lista las ocultas, salvo en la vista "Ver ocultas" (que lista solo esas).
        if ($soloOcultas) {
            $notas->soloOcultas();
        } else {
            $notas->sinOcultar();
        }

        $destinos = Contexto::opciones();
        $totalOcultas = Nota::soloOcultas()->count();

        return view('notas.index', [
            'notas' => $notas->get(),
            'colores' => ColoresDeContexto::delUsuario(),
            'destinos' => $destinos,
            'filtro' => $filtro,
            'contextoFiltro' => $contextoFiltro,
            'totalBandeja' => Nota::sinOcultar()->sinContexto()->count(),
            'totalNotas' => $soloOcultas ? $totalOcultas : Nota::sinOcultar()->count(),
            'totalOcultas' => $totalOcultas,
            'ocultasDelFiltro' => $ocultasDelFiltro,
            'totalFijadas' => Nota::sinOcultar()->where('fijada', true)->count(),
            'busqueda' => $busqueda,
            'colorFiltro' => $color,
            'soloFijadas' => $soloFijadas,
            'soloOcultas' => $soloOcultas,
        ]);
    }

    public function create(Request $request): View
    {
        return view('notas.crear', [
            'nota' => new Nota([
                'contexto_id' => $request->integer('contexto') ?: null,
                'fecha' => Carbon::hasFormat((string) $request->query('fecha'), 'Y-m-d') ? $request->query('fecha') : null,
            ]),
            'destinos' => Contexto::opciones(),
        ]);
    }

    public function store(NotaRequest $request): Response|RedirectResponse|JsonResponse
    {
        $nota = Nota::create($request->datosNota());

        if ($request->expectsJson()) {
            Aviso::guardar('Nota guardada.');

            return response()->json(['ok' => true]);
        }

        if ($request->header('HX-Request') && $request->input('origen') === 'hoy') {
            $nota->load('contexto');

            return Aviso::enHtmx(
                view('hoy._nota-rapida', ['guardada' => $nota]),
                'Nota guardada '.($nota->contexto ? 'en '.$nota->contexto->rutaCompleta() : 'en la bandeja de entrada').'.',
            );
        }

        return redirect()
            ->route($request->input('origen') === 'hoy' ? 'hoy' : 'notas.index')
            ->with(Aviso::flash('Nota guardada.'));
    }

    public function show(Nota $nota): RedirectResponse
    {
        return redirect()->route('notas.edit', $nota);
    }

    public function edit(Nota $nota): View
    {
        return view('notas.editar', [
            'nota' => $nota,
            'destinos' => Contexto::opciones(),
        ]);
    }

    public function update(NotaRequest $request, Nota $nota): RedirectResponse|JsonResponse
    {
        $nota->update($request->datosNota());

        if ($request->expectsJson()) {
            Aviso::guardar('Nota actualizada.');

            return response()->json(['ok' => true]);
        }

        return redirect()->route('notas.index')->with(Aviso::flash('Nota actualizada.'));
    }

    public function destroy(Request $request, Nota $nota): Response|RedirectResponse
    {
        $nota->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('notas.index')->with(Aviso::flash('Nota eliminada.'));
    }

    /** Fija o desfija la nota. Con HTMX devuelve solo la tarjeta actualizada. */
    public function fijar(Request $request, Nota $nota): Response|RedirectResponse
    {
        $nota->update(['fijada' => ! $nota->fijada]);

        return $this->respuestaNota($request, $nota, $nota->fijada ? 'Nota fijada.' : 'Nota desfijada.');
    }

    /** Oculta la nota de la pantalla Notas (no se borra ni deja de verse en el resto de la app). */
    public function ocultar(Request $request, Nota $nota): JsonResponse|RedirectResponse
    {
        return $this->alternarOculta($request, $nota, true, 'Nota oculta.');
    }

    /** Devuelve la nota a la pantalla Notas. */
    public function mostrar(Request $request, Nota $nota): JsonResponse|RedirectResponse
    {
        return $this->alternarOculta($request, $nota, false, 'Nota visible de nuevo.');
    }

    /** Con JS responde JSON (el aviso con "Deshacer" lo arma notas.js); sin JS vuelve a la pantalla con el aviso en la sesión. */
    private function alternarOculta(Request $request, Nota $nota, bool $oculta, string $mensaje): JsonResponse|RedirectResponse
    {
        $nota->update(['oculta' => $oculta]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'oculta' => $oculta]);
        }

        return back()->with(Aviso::flash($mensaje));
    }

    /** Mueve la nota a otro contexto (o a la bandeja de entrada si no se elige ninguno). */
    public function mover(MoverNotaRequest $request, Nota $nota): Response|RedirectResponse
    {
        $nota->update(['contexto_id' => $request->validated()['contexto_id'] ?? null]);

        return $this->respuestaNota($request, $nota, 'Nota movida.');
    }

    private function respuestaNota(Request $request, Nota $nota, string $mensaje): Response|RedirectResponse
    {
        if ($request->header('HX-Request')) {
            return Aviso::enHtmx(view('notas._nota', [
                'nota' => $nota->load(['contexto', 'columna']),
                'destinos' => Contexto::opciones(),
            ]), $mensaje);
        }

        return back()->with(Aviso::flash($mensaje));
    }
}
