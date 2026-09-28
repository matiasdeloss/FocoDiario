<?php

use App\Http\Controllers\HoyController;
use Illuminate\Support\Facades\Route;

Route::get('/', HoyController::class)->name('hoy');
