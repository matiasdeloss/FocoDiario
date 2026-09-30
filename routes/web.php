<?php

use App\Http\Controllers\ActividadController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CapturaRapidaController;
use App\Http\Controllers\ColumnaTableroController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\DetalleCalendarioController;
use App\Http\Controllers\EstudioController;
use App\Http\Controllers\HoyController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\RecomendacionController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SesionEstudioController;
use App\Http\Controllers\TableroController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\TarjetaCalendarioController;
use Illuminate\Support\Facades\Route;

// ---- Entrada (una sola cuenta; se crea con php artisan foco:usuario) ----
Route::middleware('guest')->group(function () {
    Route::get('entrar', [LoginController::class, 'create'])->name('login');
    Route::post('entrar', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('salir', [LoginController::class, 'destroy'])->name('logout');
    Route::get('csrf', [LoginController::class, 'token'])->name('csrf');

    Route::get('/', HoyController::class)->name('hoy');

    // ---- Tareas, recordatorios y registro de tiempo ----
    // Antes del resource: si no, "vencidos" se tomaría como el id de recordatorios/{recordatorio}.
    Route::get('recordatorios/vencidos', [RecordatorioController::class, 'vencidos'])->name('recordatorios.vencidos');
    Route::resource('tareas', TareaController::class);
    Route::resource('recordatorios', RecordatorioController::class);
    Route::resource('registro', RegistroController::class)->except(['create', 'show']);
    Route::patch('tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado'])->name('tareas.estado');
    Route::patch('recordatorios/{recordatorio}/avisar', [RecordatorioController::class, 'avisar'])->name('recordatorios.avisar');
    Route::patch('recordatorios/{recordatorio}/posponer', [RecordatorioController::class, 'posponer'])->name('recordatorios.posponer');
    Route::patch('recordatorios/{recordatorio}/reactivar', [RecordatorioController::class, 'reactivar'])->name('recordatorios.reactivar');

    // ---- Notas y contextos ----
    Route::resource('notas', NotaController::class);
    Route::patch('notas/{nota}/fijar', [NotaController::class, 'fijar'])->name('notas.fijar');
    Route::patch('notas/{nota}/mover', [NotaController::class, 'mover'])->name('notas.mover');
    Route::resource('contextos', ContextoController::class);

    // ---- Recomendaciones ----
    Route::get('recomendaciones', [RecomendacionController::class, 'index'])->name('recomendaciones.index');

    // ---- Estudio ----
    Route::get('estudio', [EstudioController::class, 'index'])->name('estudio.index');
    Route::get('estudio/historial', [EstudioController::class, 'historial'])->name('estudio.historial');
    Route::get('estudio/metodos', [EstudioController::class, 'metodos'])->name('estudio.metodos');
    Route::post('estudio/sesiones', [SesionEstudioController::class, 'store'])->name('estudio.sesiones.store');
    Route::patch('estudio/sesiones/{sesion}/finalizar', [SesionEstudioController::class, 'finalizar'])->name('estudio.sesiones.finalizar');
    Route::post('estudio/sesiones/{sesion}/intervalos', [SesionEstudioController::class, 'registrarIntervalo'])->name('estudio.sesiones.intervalos.store');
    Route::delete('estudio/sesiones/{sesion}', [SesionEstudioController::class, 'destroy'])->name('estudio.sesiones.destroy');

    // ---- Calendario ----
    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::get('calendario/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');
    Route::patch('calendario/tareas/{tarea}/fecha', [CalendarioController::class, 'fechaTarea'])->name('calendario.tareas.fecha');
    Route::patch('calendario/recordatorios/{recordatorio}/fecha', [CalendarioController::class, 'fechaRecordatorio'])->name('calendario.recordatorios.fecha');
    Route::patch('calendario/notas/{nota}/fecha', [CalendarioController::class, 'fechaNota'])->name('calendario.notas.fecha');
    Route::get('calendario/detalle/{tipo}/{id}', [DetalleCalendarioController::class, 'show'])
        ->whereIn('tipo', ['tarea', 'recordatorio', 'nota', 'sesion'])->whereNumber('id')->name('calendario.detalle');
    Route::post('calendario/tarjetas', [TarjetaCalendarioController::class, 'store'])->name('calendario.tarjetas.store');
    Route::get('calendario/tarjetas/{tipo}', [TarjetaCalendarioController::class, 'index'])
        ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->name('calendario.tarjetas.index');
    Route::patch('calendario/tarjetas/{tipo}/{id}', [TarjetaCalendarioController::class, 'update'])
        ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->whereNumber('id')->name('calendario.tarjetas.update');
    Route::delete('calendario/tarjetas/{tipo}/{id}', [TarjetaCalendarioController::class, 'destroy'])
        ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->whereNumber('id')->name('calendario.tarjetas.destroy');

    // ---- Tablero Kanban (vista aparte de Tareas): columnas personalizables ----
    Route::get('tablero', [TableroController::class, 'index'])->name('tablero.index');
    Route::post('tablero/columnas', [ColumnaTableroController::class, 'store'])->name('tablero.columnas.store');
    Route::patch('tablero/columnas/{columna}', [ColumnaTableroController::class, 'update'])->name('tablero.columnas.update');
    Route::delete('tablero/columnas/{columna}', [ColumnaTableroController::class, 'destroy'])->name('tablero.columnas.destroy');
    Route::patch('tablero/columnas/{columna}/mover', [ColumnaTableroController::class, 'mover'])->name('tablero.columnas.mover');
    Route::post('tablero/columnas/{columna}/tarjetas', [ColumnaTableroController::class, 'tarjeta'])->name('tablero.columnas.tarjetas.store');
    Route::patch('tareas/{tarea}/columna', [ColumnaTableroController::class, 'moverTarea'])->name('tareas.columna');

    // ---- Captura rápida de Hoy ----
    Route::post('hoy/captura', CapturaRapidaController::class)->name('hoy.captura');

    // ---- Agenda ----
    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::put('agenda/titulo', [AgendaController::class, 'titulo'])->name('agenda.titulo');
    Route::get('agenda/dia/{fecha}', [AgendaController::class, 'dia'])
        ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('agenda.dia');
    Route::patch('agenda/dia/{fecha}/layout', [CajaController::class, 'layout'])
        ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('agenda.dia.layout');
    Route::put('agenda/semana/{semana}/{zona}', [CajaController::class, 'guardarSemana'])
        ->where('semana', '\d{4}-\d{2}-\d{2}')->name('agenda.semana.guardar');
    Route::post('agenda/cajas', [CajaController::class, 'store'])->name('agenda.cajas.store');
    Route::patch('agenda/cajas/{caja}', [CajaController::class, 'update'])->name('agenda.cajas.update');
    Route::delete('agenda/cajas/{caja}', [CajaController::class, 'destroy'])->name('agenda.cajas.destroy');
    Route::post('agenda/actividades', [ActividadController::class, 'store'])->name('agenda.actividades.store');
    Route::patch('agenda/actividades/{actividad}', [ActividadController::class, 'update'])->name('agenda.actividades.update');
    Route::delete('agenda/actividades/{actividad}', [ActividadController::class, 'destroy'])->name('agenda.actividades.destroy');
});
