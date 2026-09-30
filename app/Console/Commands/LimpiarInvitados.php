<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Consulta;

/**
 * Borra invitados abandonados (y en cascada sus datos). Corre todos los días (routes/console.php):
 *  - los que no se usan hace DIAS_SIN_USO días;
 *  - los que nunca guardaron nada y tienen más de DIAS_VACIO días (bots, visitas de paso).
 */
class LimpiarInvitados extends Command
{
    public const DIAS_SIN_USO = 90;

    public const DIAS_VACIO = 2;

    /** Tablas donde un invitado pudo haber guardado algo (las columnas del tablero vienen de fábrica). */
    private const TABLAS_CON_DATOS = [
        'tareas', 'recordatorios', 'notas', 'contextos', 'bloques_tiempo', 'sesiones_estudio', 'cajas', 'ajustes',
    ];

    protected $signature = 'foco:limpiar-invitados';

    protected $description = 'Borra los invitados sin uso y los que nunca guardaron nada';

    public function handle(): int
    {
        $sinUso = $this->invitados()
            ->where(fn (Builder $q) => $q->where('ultimo_uso_en', '<', now()->subDays(self::DIAS_SIN_USO))
                ->orWhere(fn (Builder $q) => $q->whereNull('ultimo_uso_en')->where('created_at', '<', now()->subDays(self::DIAS_SIN_USO))))
            ->delete();

        $vacios = $this->invitados()->where('created_at', '<', now()->subDays(self::DIAS_VACIO));

        foreach (self::TABLAS_CON_DATOS as $tabla) {
            $vacios->whereNotExists(fn (Consulta $q) => $q->selectRaw('1')->from($tabla)->whereColumn("{$tabla}.user_id", 'users.id'));
        }

        $vacios = $vacios->delete();

        $this->info("Invitados borrados: {$sinUso} sin uso, {$vacios} sin datos.");

        return self::SUCCESS;
    }

    private function invitados(): Builder
    {
        return User::query()->where('es_invitado', true);
    }
}
