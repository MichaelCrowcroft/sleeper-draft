<?php

use App\Http\Controllers\LeagueController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/leagues', [LeagueController::class, 'index'])->name('leagues.index');
    Route::post('/leagues/sync', [LeagueController::class, 'sync'])->name('leagues.sync');
    Route::get('/leagues/{league}', [LeagueController::class, 'show'])->name('leagues.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
