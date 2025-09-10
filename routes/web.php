<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('chatgpt', 'chatgpt')->name('chatgpt');

Route::view('mcp', 'mcp')->name('mcp');

Route::view('privacy', 'privacy')->name('privacy');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Players section
    Volt::route('players', 'players.index')->name('players.index');
    Volt::route('players/{playerId}', 'players.show')->name('players.show');
    Volt::route('players/{playerId}/2024', 'players.2024')->name('players.show.2024');

    // Analytics routes
    Volt::route('analytics', 'analytics.index')->name('analytics.index');
    Volt::route('analytics/{id}', 'analytics.show')->name('analytics.show');

    // Matchups routes
    Volt::route('leagues/{leagueId}/week/{week}/matchup', 'matchups.show')->name('matchups.show');
    Volt::route('leagues/{leagueId}/matchup', 'matchups.show')->name('matchups.show.current');

    // Weekly Summary routes
    Volt::route('leagues/{leagueId}/year/{year}/week/{week}/summary', 'weekly-summary.show')->name('weekly-summary.show');

    // // Lineup Optimizer routes
    // Volt::route('leagues/{leagueId}/week/{week}/lineup-optimizer', 'lineup-optimizer')->name('lineup-optimizer.show');
    // Volt::route('leagues/{leagueId}/lineup-optimizer', 'lineup-optimizer')->name('lineup-optimizer.current');

    // Leagues index
    Volt::route('leagues', 'leagues.index')->name('leagues.index');

    // Trade Evaluator
    Volt::route('trade-evaluator', 'trade-evaluator.index')->name('trade-evaluator.index');

    // Chat - AI Fantasy Football Commissioner
    Volt::route('chat', 'chat')->name('chat');
});

require __DIR__.'/auth.php';
