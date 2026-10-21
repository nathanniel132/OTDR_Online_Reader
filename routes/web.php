<?php

use App\Http\Controllers\OtdrController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OtdrController::class, 'index'])->name('otdr.index');
Route::post('/api/otdr/parse', [OtdrController::class, 'parse'])->name('otdr.parse');
Route::get('/api/otdr/sample', [OtdrController::class, 'sample'])->name('otdr.sample');
