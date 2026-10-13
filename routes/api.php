<?php

use App\Http\Controllers\Api\FasihCheckController;
use App\Http\Controllers\Api\KartuKendaliController;
use App\Http\Controllers\Api\PmlKartuKendaliController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['kartu.kendali.token', 'throttle:300,1'])->post('/kartu-kendali', [KartuKendaliController::class, 'store']);

Route::get('/kartu-kendali', [KartuKendaliController::class, 'index']);
Route::get('/kartu-kendali/statistics', [KartuKendaliController::class, 'statistics']);
Route::get('/kartu-kendali/ppl-activity', [KartuKendaliController::class, 'pplActivity']);
Route::get('/kartu-kendali/{id}', [KartuKendaliController::class, 'show']);
Route::middleware(['kartu.kendali.token', 'throttle:300,1'])->post('/pml-kartu-kendali', [PmlKartuKendaliController::class, 'store']);
Route::get('/pml-kartu-kendali', [PmlKartuKendaliController::class, 'index']);
Route::post('/reports/perjadin', [ReportController::class, 'perjadin']);

Route::get('/fasih-checks/summary', [FasihCheckController::class, 'summary']);
Route::get('/fasih-checks/options', [FasihCheckController::class, 'options']);
Route::get('/fasih-checks/kelurahan-progress', [FasihCheckController::class, 'kelurahanProgress']);
Route::get('/fasih-checks', [FasihCheckController::class, 'index']);
Route::post('/fasih-checks/import', [FasihCheckController::class, 'import']);
Route::get('/fasih-checks/export', [FasihCheckController::class, 'export']);
Route::post('/fasih-checks/{id}/check', [FasihCheckController::class, 'check']);
Route::patch('/fasih-checks/{id}/status', [FasihCheckController::class, 'updateStatus']);
Route::get('/fasih-checks/{id}', [FasihCheckController::class, 'show']);
Route::get('/fasih-checks/{id}/logs', [FasihCheckController::class, 'logs']);
