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

    // Position-specific player pages (must come before parameterized routes)
    Volt::route('players/qb', 'players.qb')->name('players.qb');
    Volt::route('players/rb', 'players.rb')->name('players.rb');
    Volt::route('players/wr', 'players.wr')->name('players.wr');
    Volt::route('players/te', 'players.te')->name('players.te');
    Volt::route('players/def', 'players.def')->name('players.def');
    Volt::route('players/k', 'players.k')->name('players.k');

    // Player show route (must come last due to parameter)
    Volt::route('players/{playerId}', 'players.show')->name('players.show');

    // Analytics routes
    Volt::route('analytics', 'analytics.index')->name('analytics.index');
    Volt::route('analytics/{id}', 'analytics.show')->name('analytics.show');

    // Matchups routes
    Volt::route('leagues/{leagueId}/week/{week}/matchup', 'matchups.show')->name('matchups.show');
    Volt::route('leagues/{leagueId}/matchup', 'matchups.show')->name('matchups.show.current');
});

require __DIR__.'/auth.php';
