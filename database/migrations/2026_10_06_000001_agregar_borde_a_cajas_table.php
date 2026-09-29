<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Borde de cada caja de la hoja: grosor de 1 a 4 px y color propio (null = el de su actividad o el terracota del cuaderno). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->unsignedTinyInteger('borde_grosor')->default(1)->after('hecha');
            $table->string('borde_color', 7)->nullable()->after('borde_grosor');
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropColumn(['borde_grosor', 'borde_color']);
        });
    }
};
