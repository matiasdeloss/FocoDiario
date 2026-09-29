<?php

namespace App\Services\Hoy;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Calendario\TarjetasCalendario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Captura rápida de Hoy: un mismo formulario (título + descripción) que crea una tarea,
 * un recordatorio o una nota. La fecha y la hora se interpretan igual que en el calendario.
 */
class CapturaRapida
{
    public function __construct(private readonly TarjetasCalendario $tarjetas)
    {
    }

    /**
     * @param  array{tipo: string, titulo: string, descripcion?: ?string, fecha?: ?string, hora?: ?string, contexto_id?: ?int, color?: ?string}  $datos
     */
    public function crear(array $datos): Model
    {
        $fecha = $datos['fecha'] ?? null;
        $descripcion = $datos['descripcion'] ?? null;

        return match ($datos['tipo']) {
            'tarea' => Tarea::create([
                'titulo' => $datos['titulo'],
                'descripcion' => $descripcion,
                'prioridad' => PrioridadTarea::Media,
                'estado' => EstadoTarea::Pendiente,
                'fecha_limite' => $fecha,
            ]),
            // Solo el día: a las 09:00. Sin día: queda "por ubicar" en el calendario.
            'recordatorio' => Recordatorio::create([
                'mensaje' => $datos['titulo'],
                'descripcion' => $descripcion,
                'recordar_en' => $fecha ? $this->tarjetas->fechaHora(($datos['hora'] ?? null) ? $fecha.'T'.$datos['hora'] : $fecha) : null,
            ]),
            'nota' => Nota::create([
                'titulo' => $datos['titulo'],
                'contenido' => $descripcion ?? '',
                'contexto_id' => $datos['contexto_id'] ?? null,
                'fecha' => $fecha,
                'color' => $datos['color'] ?? null,
            ]),
        };
    }

    /** Confirmación breve de lo que se creó. */
    public function mensaje(Model $creado): string
    {
        return match (true) {
            $creado instanceof Tarea => 'Tarea creada.',
            $creado instanceof Recordatorio => $creado->recordar_en ? 'Recordatorio creado.' : 'Recordatorio creado, sin fecha: queda por ubicar.',
            default => 'Nota guardada '.($creado->contexto_id ? 'en '.Contexto::find($creado->contexto_id)?->rutaCompleta() : 'en la bandeja de entrada').'.',
        };
    }

    /** Texto corto de la fecha (y hora) para el chip: "Hoy", "Mañana", "12 oct", "12 oct 10:00". Vacío si no hay fecha válida. */
    public static function etiquetaFecha(?string $fecha, ?string $hora = null, ?Carbon $hoy = null): string
    {
        if (blank($fecha) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return '';
        }

        try {
            $dia = Carbon::createFromFormat('!Y-m-d', $fecha);
        } catch (\Throwable) {
            return '';
        }

        // Una fecha imposible (mes 13, 31 de febrero) se desborda: se descarta.
        if ($dia->format('Y-m-d') !== $fecha) {
            return '';
        }

        $hoy = ($hoy ?? Carbon::today())->copy()->startOfDay();
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $texto = match (true) {
            $dia->isSameDay($hoy) => 'Hoy',
            $dia->isSameDay($hoy->copy()->addDay()) => 'Mañana',
            default => $dia->day.' '.$meses[$dia->month - 1].($dia->year !== $hoy->year ? ' '.$dia->year : ''),
        };

        return $texto.(filled($hora) && preg_match('/^\d{2}:\d{2}$/', $hora) ? ' '.$hora : '');
    }
}
