<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocleTestController;
use App\Http\Controllers\Dev\StyleguideController;

/*
|--------------------------------------------------------------------------
| Routes existantes (temporaires)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
});

if (app()->environment('local')) {
    Route::get('/socle-test', [SocleTestController::class, 'index']);
    Route::get('/socle-test/csv', [SocleTestController::class, 'csv']);
}

/*
|--------------------------------------------------------------------------
| Routes nommées provisoires (stubs pour que les gabarits compilent)
| Ces routes seront remplacées aux lots 2, 4, 7.
|--------------------------------------------------------------------------
*/

// Authentification (lot 2)
Route::get('/login', fn () => abort(404))->name('login');
Route::post('/logout', fn () => abort(404))->name('logout');

// Admin (lots 4, 5, 6)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => abort(404))->name('dashboard');
    Route::get('/extractions', fn () => abort(404))->name('extractions.index');
    Route::get('/extractions/create', fn () => abort(404))->name('extractions.create');
    Route::get('/extractions/{code}/edit', fn () => abort(404))->name('extractions.edit');
});

// Espace métier (lots 7, 8)
Route::get('/dashboard', fn () => abort(404))->name('dashboard');
Route::get('/catalogue', fn () => abort(404))->name('catalogue');
Route::get('/extractions/{code}', fn () => abort(404))->name('extractions.show');

/*
|--------------------------------------------------------------------------
| Guide de style (local uniquement)
|--------------------------------------------------------------------------
*/
if (app()->environment('local')) {
    Route::get('/dev/styleguide', StyleguideController::class)->name('dev.styleguide');
}
