<?php

use App\Http\Controllers\Chat\StreamController;
use App\Http\Controllers\Chat\TitleStreamController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat/stream', StreamController::class)->name('chat.stream')->withoutMiddleware([\Laravel\Boost\Middleware\InjectBoost::class]);

Route::middleware('auth')->group(function () {
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/{chat}', [ChatController::class, 'show'])->name('chat.show');
    Route::patch('/chat/{chat}', [ChatController::class, 'update'])->name('chat.update');
    Route::delete('/chat/{chat}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::post('/chat/{chat}/stream', StreamController::class)->name('chat.show.stream')->withoutMiddleware([\Laravel\Boost\Middleware\InjectBoost::class]);
    Route::get('/chat/{chat}/title-stream', TitleStreamController::class)->name('chat.title.stream')->withoutMiddleware([\Laravel\Boost\Middleware\InjectBoost::class]);
});
