<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InboxController;
use App\Models\Business;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'businesses' => Business::query()->where('status', 'active')->orderBy('name')->get(),
    ]);
})->name('home');

// Built-in website test chat (visitors, no login).
Route::middleware('business.slug')->group(function () {
    Route::get('/chat/{slug}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/api/chat/{slug}/messages', [ChatController::class, 'send'])
        ->middleware('throttle:60,1')->name('chat.send');
    Route::get('/api/chat/{slug}/messages', [ChatController::class, 'poll'])
        ->middleware('throttle:240,1')->name('chat.poll');
});

// Staff login.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Staff inbox.
Route::middleware('staff')->prefix('admin')->group(function () {
    Route::get('/', [InboxController::class, 'index'])->name('inbox');
    Route::get('/api/conversations', [InboxController::class, 'conversations']);
    Route::get('/api/conversations/{id}', [InboxController::class, 'show'])->whereNumber('id');
    Route::post('/api/conversations/{id}/reply', [InboxController::class, 'reply'])->whereNumber('id');
    Route::post('/api/conversations/{id}/note', [InboxController::class, 'note'])->whereNumber('id');
    Route::post('/api/conversations/{id}/give-back', [InboxController::class, 'giveBack'])->whereNumber('id');
    Route::post('/api/conversations/{id}/resolve', [InboxController::class, 'resolve'])->whereNumber('id');
});

// Customers (all staff) and menu builder (owner only).
Route::middleware('staff')->prefix('admin')->group(function () {
    Route::get('/customers', [\App\Http\Controllers\CustomerController::class, 'index'])->name('customers');
});

Route::middleware(['staff', 'owner'])->prefix('admin')->group(function () {
    Route::get('/menu', [\App\Http\Controllers\MenuController::class, 'index'])->name('menu');
    Route::get('/api/menu', [\App\Http\Controllers\MenuController::class, 'show']);
    Route::put('/api/menu', [\App\Http\Controllers\MenuController::class, 'save']);
    Route::post('/api/menu/publish', [\App\Http\Controllers\MenuController::class, 'publish']);
    Route::post('/api/menu/reset', [\App\Http\Controllers\MenuController::class, 'reset']);
    Route::post('/api/menu/preview', [\App\Http\Controllers\MenuController::class, 'preview']);
    Route::get('/api/menu/stats', [\App\Http\Controllers\MenuController::class, 'stats']);
});
