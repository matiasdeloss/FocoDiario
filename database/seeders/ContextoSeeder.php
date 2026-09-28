<?php

namespace Database\Seeders;

use App\Enums\TipoContexto;
use App\Models\Contexto;
use Illuminate\Database\Seeder;

class ContextoSeeder extends Seeder
{
    /**
     * Entornos iniciales. Se puede ejecutar más de una vez sin duplicar.
     */
    public function run(): void
    {
        foreach (['Carrera', 'Vida cotidiana', 'Proyectos personales'] as $nombre) {
            Contexto::firstOrCreate(
                ['nombre' => $nombre, 'contexto_padre_id' => null],
                ['tipo' => TipoContexto::Entorno],
            );
        }
    }
}
