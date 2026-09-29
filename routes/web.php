<?php

use App\Http\Controllers\HoyController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\TareaController;
use Illuminate\Support\Facades\Route;

Route::get('/', HoyController::class)->name('hoy');

Route::resource('tareas', TareaController::class);
Route::resource('recordatorios', RecordatorioController::class);
Route::resource('registro', RegistroController::class);

// Acciones extra de los módulos
Route::patch('tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado'])->name('tareas.estado');
Route::patch('recordatorios/{recordatorio}/avisar', [RecordatorioController::class, 'avisar'])->name('recordatorios.avisar');
Route::patch('recordatorios/{recordatorio}/reactivar', [RecordatorioController::class, 'reactivar'])->name('recordatorios.reactivar');

// ---- Notas y contextos (agente de notas) ----
Route::resource('notas', \App\Http\Controllers\NotaController::class);
Route::patch('notas/{nota}/fijar', [\App\Http\Controllers\NotaController::class, 'fijar'])->name('notas.fijar');
Route::patch('notas/{nota}/mover', [\App\Http\Controllers\NotaController::class, 'mover'])->name('notas.mover');
Route::resource('contextos', \App\Http\Controllers\ContextoController::class);

// ---- Recomendaciones (agente de recomendaciones) ----
Route::get('recomendaciones', [\App\Http\Controllers\RecomendacionController::class, 'index'])->name('recomendaciones.index');

// ---- Estudio (agente de estudio) ----
Route::get('estudio', [\App\Http\Controllers\EstudioController::class, 'index'])->name('estudio.index');
Route::get('estudio/historial', [\App\Http\Controllers\EstudioController::class, 'historial'])->name('estudio.historial');
Route::get('estudio/metodos', [\App\Http\Controllers\EstudioController::class, 'metodos'])->name('estudio.metodos');
Route::post('estudio/sesiones', [\App\Http\Controllers\SesionEstudioController::class, 'store'])->name('estudio.sesiones.store');
Route::patch('estudio/sesiones/{sesion}/finalizar', [\App\Http\Controllers\SesionEstudioController::class, 'finalizar'])->name('estudio.sesiones.finalizar');
Route::post('estudio/sesiones/{sesion}/intervalos', [\App\Http\Controllers\SesionEstudioController::class, 'registrarIntervalo'])->name('estudio.sesiones.intervalos.store');
Route::delete('estudio/sesiones/{sesion}', [\App\Http\Controllers\SesionEstudioController::class, 'destroy'])->name('estudio.sesiones.destroy');

// ---- Calendario (agente de calendario) ----
Route::get('calendario', [\App\Http\Controllers\CalendarioController::class, 'index'])->name('calendario.index');
Route::get('calendario/eventos', [\App\Http\Controllers\CalendarioController::class, 'eventos'])->name('calendario.eventos');
Route::patch('calendario/tareas/{tarea}/fecha', [\App\Http\Controllers\CalendarioController::class, 'fechaTarea'])->name('calendario.tareas.fecha');
Route::patch('calendario/recordatorios/{recordatorio}/fecha', [\App\Http\Controllers\CalendarioController::class, 'fechaRecordatorio'])->name('calendario.recordatorios.fecha');
Route::patch('calendario/notas/{nota}/fecha', [\App\Http\Controllers\CalendarioController::class, 'fechaNota'])->name('calendario.notas.fecha');
Route::get('calendario/detalle/{tipo}/{id}', [\App\Http\Controllers\DetalleCalendarioController::class, 'show'])
    ->whereIn('tipo', ['tarea', 'recordatorio', 'nota', 'sesion'])->whereNumber('id')->name('calendario.detalle');
Route::post('calendario/tarjetas', [\App\Http\Controllers\TarjetaCalendarioController::class, 'store'])->name('calendario.tarjetas.store');
Route::get('calendario/tarjetas/{tipo}', [\App\Http\Controllers\TarjetaCalendarioController::class, 'index'])
    ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->name('calendario.tarjetas.index');
Route::patch('calendario/tarjetas/{tipo}/{id}', [\App\Http\Controllers\TarjetaCalendarioController::class, 'update'])
    ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->whereNumber('id')->name('calendario.tarjetas.update');
Route::delete('calendario/tarjetas/{tipo}/{id}', [\App\Http\Controllers\TarjetaCalendarioController::class, 'destroy'])
    ->whereIn('tipo', ['tarea', 'recordatorio', 'nota'])->whereNumber('id')->name('calendario.tarjetas.destroy');

// ---- Tablero de tareas: columnas personalizables ----
Route::post('tablero/columnas', [\App\Http\Controllers\ColumnaTableroController::class, 'store'])->name('tablero.columnas.store');
Route::patch('tablero/columnas/{columna}', [\App\Http\Controllers\ColumnaTableroController::class, 'update'])->name('tablero.columnas.update');
Route::delete('tablero/columnas/{columna}', [\App\Http\Controllers\ColumnaTableroController::class, 'destroy'])->name('tablero.columnas.destroy');
Route::patch('tablero/columnas/{columna}/mover', [\App\Http\Controllers\ColumnaTableroController::class, 'mover'])->name('tablero.columnas.mover');
Route::post('tablero/columnas/{columna}/tarjetas', [\App\Http\Controllers\ColumnaTableroController::class, 'tarjeta'])->name('tablero.columnas.tarjetas.store');
Route::patch('tareas/{tarea}/columna', [\App\Http\Controllers\ColumnaTableroController::class, 'moverTarea'])->name('tareas.columna');

// ---- Captura rápida de Hoy ----
Route::post('hoy/captura', \App\Http\Controllers\CapturaRapidaController::class)->name('hoy.captura');

// ---- Agenda ----
Route::get('agenda', [\App\Http\Controllers\AgendaController::class, 'index'])->name('agenda.index');
Route::put('agenda/titulo', [\App\Http\Controllers\AgendaController::class, 'titulo'])->name('agenda.titulo');
Route::get('agenda/dia/{fecha}', [\App\Http\Controllers\AgendaController::class, 'dia'])
    ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('agenda.dia');
Route::patch('agenda/dia/{fecha}/layout', [\App\Http\Controllers\CajaController::class, 'layout'])
    ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('agenda.dia.layout');
Route::put('agenda/semana/{semana}/{zona}', [\App\Http\Controllers\CajaController::class, 'guardarSemana'])
    ->where('semana', '\d{4}-\d{2}-\d{2}')->name('agenda.semana.guardar');
Route::post('agenda/cajas', [\App\Http\Controllers\CajaController::class, 'store'])->name('agenda.cajas.store');
Route::patch('agenda/cajas/{caja}', [\App\Http\Controllers\CajaController::class, 'update'])->name('agenda.cajas.update');
Route::delete('agenda/cajas/{caja}', [\App\Http\Controllers\CajaController::class, 'destroy'])->name('agenda.cajas.destroy');
Route::post('agenda/actividades', [\App\Http\Controllers\ActividadController::class, 'store'])->name('agenda.actividades.store');
Route::patch('agenda/actividades/{actividad}', [\App\Http\Controllers\ActividadController::class, 'update'])->name('agenda.actividades.update');
Route::delete('agenda/actividades/{actividad}', [\App\Http\Controllers\ActividadController::class, 'destroy'])->name('agenda.actividades.destroy');
