<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocleTestController;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment('local')) {
    Route::get('/socle-test', [SocleTestController::class, 'index']);
    Route::get('/socle-test/csv', [SocleTestController::class, 'csv']);
}
