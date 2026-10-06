<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varios tableros por usuario. Cada usuario pasa a tener un tablero "Principal" que se queda con sus columnas de
 * hoy (y con sus tareas), y todo tablero lleva una primera columna fija "Sin asignar" (de categoría pendiente) donde
 * caen las tarjetas nuevas. La columna fija se marca con `columnas_tablero.fija`, no por su nombre.
 */
return new class extends Migration
{
    /**
     * En SQLite (desarrollo y tests) cambiar una columna rehace la tabla y, con las claves foráneas activas, las tareas pierden su
     * columna (ON DELETE SET NULL). Se guarda y se vuelve a poner. En MySQL y PostgreSQL el esquema se altera en el lugar y no hace falta.
     *
     * @return array<int, int>
     */
    private function guardarColumnasDeTareas(): array
    {
        return DB::getDriverName() === 'sqlite' ? DB::table('tareas')->whereNotNull('columna_id')->pluck('columna_id', 'id')->all() : [];
    }

    /** @param  array<int, int>  $guardadas */
    private function restaurarColumnasDeTareas(array $guardadas): void
    {
        foreach (collect($guardadas)->groupBy(fn ($columna) => $columna, preserveKeys: true) as $columna => $tareas) {
            if (DB::table('columnas_tablero')->where('id', $columna)->exists()) {
                DB::table('tareas')->whereIn('id', $tareas->keys())->update(['columna_id' => $columna]);
            }
        }
    }

    public function up(): void
    {
        $guardadas = $this->guardarColumnasDeTareas();

        Schema::create('tableros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->boolean('principal')->default(false);
            $table->unsignedInteger('posicion')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'posicion']);
        });

        Schema::table('columnas_tablero', function (Blueprint $table) {
            $table->foreignId('tablero_id')->nullable()->after('user_id')->constrained('tableros')->cascadeOnDelete();
            $table->boolean('fija')->default(false)->after('posicion');
        });

        $ahora = now();

        foreach (DB::table('users')->pluck('id') as $usuario) {
            $tablero = DB::table('tableros')->insertGetId([
                'user_id' => $usuario, 'nombre' => 'Principal', 'principal' => true, 'posicion' => 0,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);

            $hayColumnas = DB::table('columnas_tablero')->where('user_id', $usuario)->exists();

            // "Sin asignar" va primera: el resto se corre un lugar.
            DB::table('columnas_tablero')->where('user_id', $usuario)->update(['tablero_id' => $tablero]);
            DB::table('columnas_tablero')->where('user_id', $usuario)->increment('posicion');
            DB::table('columnas_tablero')->insert([
                'user_id' => $usuario, 'tablero_id' => $tablero, 'nombre' => 'Sin asignar', 'categoria' => 'pendiente',
                'posicion' => 0, 'fija' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);

            if (! $hayColumnas) {
                foreach ([['En progreso', 'en_progreso'], ['Completada', 'completada']] as $posicion => [$nombre, $categoria]) {
                    DB::table('columnas_tablero')->insert([
                        'user_id' => $usuario, 'tablero_id' => $tablero, 'nombre' => $nombre, 'categoria' => $categoria,
                        'posicion' => $posicion + 1, 'fija' => false, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
        }

        Schema::table('columnas_tablero', function (Blueprint $table) {
            $table->foreignId('tablero_id')->nullable(false)->change();
        });

        $this->restaurarColumnasDeTareas($guardadas);
    }

    public function down(): void
    {
        // Antes de este cambio había un solo tablero por usuario: queda el principal (o, si no hay, el primero) sin su "Sin asignar",
        // y las tarjetas de los demás tableros pasan a su columna de la misma categoría (o, si no hay, a la primera no fija).
        $tablas = collect(['tareas', 'notas'])->filter(fn ($tabla) => Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'columna_id'));

        foreach (DB::table('tableros')->distinct()->pluck('user_id') as $usuario) {
            $tablero = DB::table('tableros')->where('user_id', $usuario)->orderByDesc('principal')->orderBy('posicion')->orderBy('id')->value('id');
            $propias = DB::table('columnas_tablero')->where('tablero_id', $tablero)->where('fija', false)->orderBy('posicion')->orderBy('id')->get();
            $ajenas = DB::table('columnas_tablero')->where('user_id', $usuario)
                ->where(fn ($q) => $q->where('tablero_id', '!=', $tablero)->orWhere('fija', true))->get();

            foreach ($ajenas as $columna) {
                $destino = ($propias->firstWhere('categoria', $columna->categoria) ?? $propias->first())?->id;

                foreach ($tablas as $tabla) {
                    DB::table($tabla)->where('columna_id', $columna->id)->update(['columna_id' => $destino]);
                }
            }

            DB::table('columnas_tablero')->whereIn('id', $ajenas->pluck('id'))->delete();
            // Sin la columna fija, las del principal vuelven a empezar en 0.
            DB::table('columnas_tablero')->where('tablero_id', $tablero)->decrement('posicion');
        }

        $guardadas = $this->guardarColumnasDeTareas();

        Schema::table('columnas_tablero', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tablero_id');
            $table->dropColumn('fija');
        });

        $this->restaurarColumnasDeTareas($guardadas);

        Schema::dropIfExists('tableros');
    }
};
