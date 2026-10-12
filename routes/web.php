<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SocleTestController;
use App\Http\Controllers\Dev\StyleguideController;
use App\Http\Controllers\AdminHomeController;
use App\Http\Controllers\Admin\ExtractionAdminController;

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
        Route::get('/', AdminHomeController::class)->name('dashboard');
        Route::get('/extractions', [ExtractionAdminController::class, 'index'])->name('extractions.index');
        Route::get('/extractions/create', [ExtractionAdminController::class, 'create'])->name('extractions.create');
        Route::post('/extractions', [ExtractionAdminController::class, 'store'])->name('extractions.store');
        Route::post('/extractions/verifier', [ExtractionAdminController::class, 'verifier'])->name('extractions.verifier');
        Route::get('/extractions/{code}/edit', [ExtractionAdminController::class, 'edit'])->name('extractions.edit');
        Route::put('/extractions/{code}', [ExtractionAdminController::class, 'update'])->name('extractions.update');
        Route::patch('/extractions/{code}/statut', [ExtractionAdminController::class, 'toggleStatut'])->name('extractions.toggle-statut');
        Route::get('/extractions/{code}/test', fn (string $code) => view('admin.extractions.soon', ['titre' => "Tester l'extraction {$code}"]))->name('extractions.test');
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
