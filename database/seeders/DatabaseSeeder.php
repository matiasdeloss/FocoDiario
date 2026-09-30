<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Los datos de ejemplo quedan a nombre de la cuenta admin: se siembran "como" ella, así cada modelo
     * completa su user_id (PerteneceAUsuario). Por eso no se desactivan los eventos de los modelos.
     */
    public function run(): void
    {
        $this->call([AdminSeeder::class, CategoriaSeeder::class]);

        Auth::setUser(User::where('email', AdminSeeder::EMAIL)->firstOrFail());

        $this->call([
            ContextoSeeder::class,
            EstudianteTecnologiaSeeder::class,
        ]);
    }
}
