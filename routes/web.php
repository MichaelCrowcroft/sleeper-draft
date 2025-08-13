<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/api/chats', [ChatController::class, 'index'])->name('api.chats.index');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
