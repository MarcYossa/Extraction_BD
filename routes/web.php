<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SocleTestController;
use App\Http\Controllers\Dev\StyleguideController;
use App\Http\Controllers\AdminHomeController;
use App\Http\Controllers\Admin\ExtractionAdminController;
use App\Http\Controllers\Admin\ExtractionTestController;
use App\Http\Controllers\Admin\ExtractionStatutController;

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

use App\Http\Controllers\Metier\DashboardController;
use App\Http\Controllers\Metier\CatalogueController;
use App\Http\Controllers\Metier\ExtractionController;

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
        Route::patch('/extractions/{code}/statut', [ExtractionStatutController::class, 'updateStatut'])->name('extractions.statut');
        Route::patch('/extractions/{code}/toggle-statut', [ExtractionStatutController::class, 'updateStatut'])->name('extractions.toggle-statut');
        Route::get('/extractions/{code}/test', [ExtractionTestController::class, 'testForm'])->name('extractions.test');
        Route::post('/extractions/{code}/test', [ExtractionTestController::class, 'executeTest'])->name('extractions.test.execute');
        Route::get('/extractions/{code}/test/resultat/{jeton}', [ExtractionTestController::class, 'resultat'])->name('extractions.test.resultat');
    });

    // Espace Utilisateur Métier — réservé au rôle 'user' (D14)
    Route::middleware('user')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/catalogue', CatalogueController::class)->name('catalogue');
        Route::get('/extractions/{code}', [ExtractionController::class, 'show'])->name('extractions.show');
        Route::post('/extractions/{code}/execute', [ExtractionController::class, 'execute'])->name('extractions.execute');
        Route::get('/extractions/{code}/resultats/{jeton}', [ExtractionController::class, 'resultats'])->name('extractions.resultats');
        Route::get('/extractions/{code}/csv/{jeton}', [ExtractionController::class, 'csv'])->name('extractions.csv');
    });


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
