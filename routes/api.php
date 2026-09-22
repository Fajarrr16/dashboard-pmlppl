<?php

use App\Http\Controllers\Api\KartuKendaliController;
use Illuminate\Support\Facades\Route;

Route::middleware(['kartu.kendali.token', 'throttle:60,1'])->post('/kartu-kendali', [KartuKendaliController::class, 'store']);

Route::get('/kartu-kendali', [KartuKendaliController::class, 'index']);
Route::get('/kartu-kendali/statistics', [KartuKendaliController::class, 'statistics']);
Route::get('/kartu-kendali/{id}', [KartuKendaliController::class, 'show']);
