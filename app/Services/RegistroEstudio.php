<?php

namespace App\Services;

use App\Enums\OrigenBloque;
use App\Enums\TipoCategoria;
use App\Enums\TipoIntervalo;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Models\IntervaloEstudio;
use App\Models\SesionEstudio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegistroEstudio
{
    /** Los intervalos más cortos que esto quedan en el historial pero no generan bloque de tiempo. */
    public const SEGUNDOS_MINIMOS_BLOQUE = 60;

    /**
     * Guarda un intervalo de la sesión y, si dura lo suficiente, su bloque de tiempo:
     * el foco (completo o interrumpido, con su duración real) va a "Estudio" y el
     * descanso y el tiempo libre van a "Descanso". Repetir la misma clave no duplica nada.
     *
     * @param  array{tipo: string, clave: string, inicio: string, fin: string, planificado_min?: ?int, pausado_seg?: ?int, completado: bool}  $datos
     */
    public function registrarIntervalo(SesionEstudio $sesion, array $datos): IntervaloEstudio
    {
        $existente = $sesion->intervalos()->where('clave', $datos['clave'])->first();

        if ($existente) {
            return $existente;
        }

        $tipo = TipoIntervalo::from($datos['tipo']);
        $inicio = $this->aHoraLocal($datos['inicio']);
        $fin = $this->aHoraLocal($datos['fin']);
        $pausadoSeg = (int) ($datos['pausado_seg'] ?? 0);
        $duracionSeg = max(0, (int) $inicio->diffInSeconds($fin, true) - $pausadoSeg);

        return DB::transaction(function () use ($sesion, $datos, $tipo, $inicio, $fin, $pausadoSeg, $duracionSeg) {
            $bloque = null;

            if ($duracionSeg >= self::SEGUNDOS_MINIMOS_BLOQUE) {
                $bloque = BloqueTiempo::create([
                    'categoria_id' => $this->categoriaPara($tipo)->id,
                    'tarea_id' => $tipo === TipoIntervalo::Foco ? $sesion->tarea_id : null,
                    'inicio' => $inicio,
                    // Las pausas no cuentan: el bloque dura lo trabajado de verdad.
                    'fin' => $inicio->copy()->addSeconds($duracionSeg),
                    'origen' => OrigenBloque::Pomodoro,
                ]);
            }

            return $sesion->intervalos()->create([
                'tipo' => $tipo,
                'clave' => $datos['clave'],
                'inicio' => $inicio,
                'fin' => $fin,
                'planificado_min' => $datos['planificado_min'] ?? null,
                'pausado_seg' => $pausadoSeg,
                'duracion_seg' => $duracionSeg,
                'completado' => (bool) $datos['completado'],
                'bloque_tiempo_id' => $bloque?->id,
            ]);
        });
    }

    /** Borra la sesión junto con los bloques de tiempo que generó. */
    public function borrarSesion(SesionEstudio $sesion): void
    {
        DB::transaction(function () use ($sesion) {
            BloqueTiempo::whereIn('id', $sesion->intervalos()->whereNotNull('bloque_tiempo_id')->pluck('bloque_tiempo_id'))->delete();
            $sesion->delete();
        });
    }

    private function categoriaPara(TipoIntervalo $tipo): Categoria
    {
        return $tipo === TipoIntervalo::Foco
            ? Categoria::firstOrCreate(['nombre' => 'Estudio'], ['tipo' => TipoCategoria::Productiva])
            : Categoria::firstOrCreate(['nombre' => 'Descanso'], ['tipo' => TipoCategoria::Descanso]);
    }

    /** El navegador manda fechas ISO en UTC; en la base se guardan en la zona de la app. */
    private function aHoraLocal(string $fecha): Carbon
    {
        return Carbon::parse($fecha)->setTimezone(config('app.timezone'));
    }
}
