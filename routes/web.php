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
    Volt::route('players/qb', 'players.qb')->name('players.qb');
    Volt::route('players/rb', 'players.rb')->name('players.rb');
    Volt::route('players/wr', 'players.wr')->name('players.wr');
    Volt::route('players/te', 'players.te')->name('players.te');
    Volt::route('players/def', 'players.def')->name('players.def');
    Volt::route('players/k', 'players.k')->name('players.k');
    Volt::route('players/{playerId}', 'players.show')->name('players.show');
    Volt::route('players/{playerId}/2024', 'players.2024')->name('players.show.2024');

    // Analytics routes
    Volt::route('analytics', 'analytics.index')->name('analytics.index');
    Volt::route('analytics/{id}', 'analytics.show')->name('analytics.show');

    // Matchups routes
    Volt::route('leagues/{leagueId}/week/{week}/matchup', 'matchups.show')->name('matchups.show');
    Volt::route('leagues/{leagueId}/matchup', 'matchups.show')->name('matchups.show.current');

    // // Lineup Optimizer routes
    // Volt::route('leagues/{leagueId}/week/{week}/lineup-optimizer', 'lineup-optimizer')->name('lineup-optimizer.show');
    // Volt::route('leagues/{leagueId}/lineup-optimizer', 'lineup-optimizer')->name('lineup-optimizer.current');

    // Leagues index
    Volt::route('leagues', 'leagues.index')->name('leagues.index');
});

require __DIR__.'/auth.php';
