<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cuenta inicial para entrar a la app: admin@focodiario.com / password.
 * Es una contraseña conocida: antes de publicar, cambiala con php artisan foco:usuario admin@focodiario.com.
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'admin@focodiario.com';

    public function run(): void
    {
        // Si la cuenta ya existe no se le pisa la contraseña (pudo haberse cambiado).
        User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Admin', 'password' => 'password'],
        );
    }
}
