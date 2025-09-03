<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('chatgpt', 'chatgpt')->name('chatgpt');

Route::view('mcp', 'mcp')->name('mcp');

Route::view('privacy', 'privacy')->name('privacy');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Analytics routes
    Route::get('analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/filter', [\App\Http\Controllers\AnalyticsController::class, 'filter'])->name('analytics.filter');
    Route::get('analytics/{id}', [\App\Http\Controllers\AnalyticsController::class, 'show'])->name('analytics.show');
});

require __DIR__.'/auth.php';
