<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SocleTestController;
use App\Http\Controllers\Dev\StyleguideController;

/*
|--------------------------------------------------------------------------
| Racine & Redirection
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authentification (Lot 2)
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Routes protégées par l'authentification
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Administration technique (contrôle d'accès rôle admin)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
        Route::get('/extractions', fn () => abort(404))->name('extractions.index');
        Route::get('/extractions/create', fn () => abort(404))->name('extractions.create');
        Route::get('/extractions/{code}/edit', fn () => abort(404))->name('extractions.edit');
    });

    // Espace Utilisateur Métier
    Route::get('/dashboard', fn () => view('metier.dashboard'))->name('dashboard');
    Route::get('/catalogue', fn () => abort(404))->name('catalogue');
    Route::get('/extractions/{code}', fn () => abort(404))->name('extractions.show');

});

/*
|--------------------------------------------------------------------------
| Routes de développement local
|--------------------------------------------------------------------------
*/
if (app()->environment('local')) {
    Route::get('/socle-test', [SocleTestController::class, 'index']);
    Route::get('/socle-test/csv', [SocleTestController::class, 'csv']);
    Route::get('/dev/styleguide', StyleguideController::class)->name('dev.styleguide');
}
