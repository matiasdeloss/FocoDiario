<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Crea la cuenta de la app o le cambia la contraseña. La contraseña se pide por consola: nunca queda en el código ni en el historial. */
class CrearUsuario extends Command
{
    protected $signature = 'foco:usuario {email : Email con el que se entra} {--nombre= : Nombre visible}';

    protected $description = 'Crea la cuenta de FocoDiario o le cambia la contraseña';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->secret('Contraseña (mínimo 8 caracteres)');
        $repetida = (string) $this->secret('Repetila');

        $validador = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $repetida],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'confirmed', Password::min(8)]],
            [
                'email.email' => 'El email no es válido.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
            ],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::query()->firstOrNew(['email' => $email]);
        $existia = $usuario->exists;

        $usuario->fill([
            'name' => $this->option('nombre') ?: ($usuario->name ?: strstr($email, '@', true)),
            'password' => $password,
        ])->save();

        $this->info($existia ? "Contraseña actualizada para {$email}." : "Cuenta creada: {$email}.");

        return self::SUCCESS;
    }
}
