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

// ---- Tablero de tareas (agente de tablero) ----
