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

    // Analytics routes
    Volt::route('analytics', 'analytics.index')->name('analytics.index');
    Volt::route('analytics/{id}', 'analytics.show')->name('analytics.show');
});

require __DIR__.'/auth.php';
