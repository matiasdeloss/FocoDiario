<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * La app pasa de una sola cuenta a varias, con invitados: cada dato tiene dueño (user_id).
 * Lo que ya había queda a nombre de admin@focodiario.com (o del primer usuario).
 */
return new class extends Migration
{
    /** Tablas con datos de cada usuario. `categorias` sigue siendo un catálogo común. */
    private const TABLAS = [
        'tareas', 'recordatorios', 'notas', 'contextos', 'bloques_tiempo',
        'sesiones_estudio', 'intervalos_estudio', 'cajas', 'columnas_tablero',
    ];

    private const EMAIL_ADMIN = 'admin@focodiario.com';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->boolean('es_invitado')->default(false)->after('password');
            $table->timestamp('ultimo_uso_en')->nullable()->after('es_invitado');
            $table->index(['es_invitado', 'ultimo_uso_en']);
        });

        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            });
        }

        $dueno = $this->dueno();

        if ($dueno !== null) {
            foreach (self::TABLAS as $tabla) {
                DB::table($tabla)->whereNull('user_id')->update(['user_id' => $dueno]);
            }
        } else {
            // Sin usuarios ni datos propios solo quedan las columnas de fábrica sin dueño: cada usuario tendrá las suyas.
            DB::table('columnas_tablero')->whereNull('user_id')->delete();
        }

        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }

        $this->columnasParaLasDemasCuentas();

        // Nombre único dentro del mismo padre, ahora por usuario. El índice simple sostiene la FK del padre (MySQL).
        Schema::table('contextos', function (Blueprint $table) {
            $table->index('contexto_padre_id');
        });
        Schema::table('contextos', function (Blueprint $table) {
            $table->dropUnique(['contexto_padre_id', 'nombre']);
            $table->unique(['user_id', 'contexto_padre_id', 'nombre']);
        });

        $this->rehacerAjustes($dueno);
    }

    public function down(): void
    {
        $dueno = $this->dueno(crear: false);

        // Los invitados (y en cascada sus datos) no existían antes de esta migración.
        DB::table('users')->where('es_invitado', true)->delete();

        // Con una sola cuenta vuelve a haber un único tablero: las columnas de las demás cuentas se unen a las del dueño.
        if ($dueno !== null) {
            foreach (DB::table('columnas_tablero')->where('user_id', '!=', $dueno)->get() as $columna) {
                $destino = DB::table('columnas_tablero')->where('user_id', $dueno)->where('categoria', $columna->categoria)->orderBy('posicion')->value('id');

                if ($destino !== null) {
                    DB::table('tareas')->where('columna_id', $columna->id)->update(['columna_id' => $destino]);
                    DB::table('columnas_tablero')->where('id', $columna->id)->delete();
                }
            }
        }

        Schema::create('ajustes_viejos', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
        if ($dueno !== null) {
            DB::table('ajustes')->where('user_id', $dueno)->orderBy('id')->get()->each(fn ($fila) => DB::table('ajustes_viejos')->insert([
                'clave' => $fila->clave, 'valor' => $fila->valor, 'created_at' => $fila->created_at, 'updated_at' => $fila->updated_at,
            ]));
        }
        Schema::drop('ajustes');
        Schema::rename('ajustes_viejos', 'ajustes');

        Schema::table('contextos', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'contexto_padre_id', 'nombre']);
            $table->unique(['contexto_padre_id', 'nombre']);
        });
        Schema::table('contextos', function (Blueprint $table) {
            $table->dropIndex(['contexto_padre_id']);
        });

        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['es_invitado', 'ultimo_uso_en']);
            $table->dropColumn(['es_invitado', 'ultimo_uso_en']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }

    /**
     * Dueño de los datos que ya existían: la cuenta admin, o el primer usuario. Si no hay ninguno
     * pero sí datos propios, se crea la cuenta admin (contraseña "password": cambiarla con foco:usuario).
     */
    private function dueno(bool $crear = true): ?int
    {
        $id = DB::table('users')->where('email', self::EMAIL_ADMIN)->value('id')
            ?? DB::table('users')->where('es_invitado', false)->orderBy('id')->value('id');

        if ($id !== null || ! $crear) {
            return $id;
        }

        $hayDatos = collect(self::TABLAS)->reject(fn ($t) => $t === 'columnas_tablero')->contains(fn ($t) => DB::table($t)->exists())
            || DB::table('ajustes')->exists();

        if (! $hayDatos) {
            return null;
        }

        return DB::table('users')->insertGetId([
            'name' => 'Admin',
            'email' => self::EMAIL_ADMIN,
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Las cuentas que ya existían y no se quedaron con los datos también necesitan sus columnas de fábrica. */
    private function columnasParaLasDemasCuentas(): void
    {
        $ahora = now();

        foreach (DB::table('users')->whereNotIn('id', DB::table('columnas_tablero')->select('user_id'))->pluck('id') as $usuario) {
            foreach ([['Pendiente', 'pendiente'], ['En progreso', 'en_progreso'], ['Completada', 'completada']] as $posicion => [$nombre, $categoria]) {
                DB::table('columnas_tablero')->insert([
                    'user_id' => $usuario, 'nombre' => $nombre, 'categoria' => $categoria, 'posicion' => $posicion,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    /** `ajustes` tenía la clave como primaria (uno para toda la app): pasa a ser por usuario. */
    private function rehacerAjustes(?int $dueno): void
    {
        Schema::create('ajustes_nuevos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('clave', 60);
            $table->text('valor')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'clave']);
        });

        if ($dueno !== null) {
            DB::table('ajustes')->get()->each(fn ($fila) => DB::table('ajustes_nuevos')->insert([
                'user_id' => $dueno, 'clave' => $fila->clave, 'valor' => $fila->valor,
                'created_at' => $fila->created_at, 'updated_at' => $fila->updated_at,
            ]));
        }

        Schema::drop('ajustes');
        Schema::rename('ajustes_nuevos', 'ajustes');
    }
};
