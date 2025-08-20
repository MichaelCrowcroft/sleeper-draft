<?php

use App\Http\Controllers\LeagueController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();
    $mcpTokens = $user->tokens()
        ->where('name', 'LIKE', 'MCP%')
        ->orderBy('created_at', 'asc')
        ->get(['id', 'name', 'token'])
        ->map(fn($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'token' => $token->token,
            'token_preview' => substr($token->token, 0, 8).'...'.substr($token->token, -8)
        ]);

    $firstToken = $mcpTokens->first();

    return Inertia::render('Dashboard', [
        'hasMcpTokens' => $mcpTokens->isNotEmpty(),
        'mcpTokens' => $mcpTokens,
        'firstToken' => $firstToken,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/leagues', [LeagueController::class, 'index'])->name('leagues.index');
    Route::post('/leagues/sync', [LeagueController::class, 'sync'])->name('leagues.sync');
    Route::get('/leagues/{league}', [LeagueController::class, 'show'])->name('leagues.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
