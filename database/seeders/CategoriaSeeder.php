<?php

namespace Database\Seeders;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Categorías iniciales. Se puede ejecutar más de una vez sin duplicar.
     */
    public function run(): void
    {
        $categorias = [
            'Estudio' => TipoCategoria::Productiva,
            'Proyectos' => TipoCategoria::Productiva,
            'Ejercicio' => TipoCategoria::Productiva,
            'Entretenimiento' => TipoCategoria::Ocio,
            'Redes sociales' => TipoCategoria::Ocio,
            'Descanso' => TipoCategoria::Descanso,
            'Comidas' => TipoCategoria::Descanso,
        ];

        foreach ($categorias as $nombre => $tipo) {
            Categoria::updateOrCreate(['nombre' => $nombre], ['tipo' => $tipo]);
        }
    }
}
