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
        ->middleware('throttle:chat-send')->name('chat.send');
    Route::get('/api/chat/{slug}/messages', [ChatController::class, 'poll'])
        ->middleware('throttle:chat-poll')->name('chat.poll');
});

// Staff login.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
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
    Route::post('/api/conversations/{id}/tag', [InboxController::class, 'tag'])->whereNumber('id');
    Route::post('/api/conversations/{id}/assign', [InboxController::class, 'assign'])->whereNumber('id');
    Route::post('/api/conversations/{id}/template', [\App\Http\Controllers\TemplateController::class, 'send'])->whereNumber('id');
    Route::get('/api/quick-replies', [\App\Http\Controllers\LibraryController::class, 'list']);
});

// Customers (all staff) and menu builder (owner only).
Route::middleware('staff')->prefix('admin')->group(function () {
    Route::get('/customers', [\App\Http\Controllers\CustomerController::class, 'index'])->name('customers');
    Route::get('/customers/{id}', [\App\Http\Controllers\CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
    Route::post('/customers/bulk', [\App\Http\Controllers\CustomerController::class, 'bulk']);
    Route::post('/customers/{id}', [\App\Http\Controllers\CustomerController::class, 'update'])->whereNumber('id');
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

// Schedule (all staff) and booking setup (owner only).
Route::middleware('staff')->prefix('admin')->group(function () {
    Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('schedule');
    Route::post('/schedule/{id}/cancel', [\App\Http\Controllers\ScheduleController::class, 'cancel'])->whereNumber('id');
    Route::post('/schedule/{id}/mark', [\App\Http\Controllers\ScheduleController::class, 'mark'])->whereNumber('id');
});

Route::middleware(['staff', 'owner'])->prefix('admin')->group(function () {
    $c = \App\Http\Controllers\BookingSetupController::class;
    Route::get('/booking-setup', [$c, 'index'])->name('booking.setup');
    Route::post('/booking-setup/rules', [$c, 'saveRules']);
    Route::post('/booking-setup/services', [$c, 'storeService']);
    Route::post('/booking-setup/services/{id}', [$c, 'updateService'])->whereNumber('id');
    Route::post('/booking-setup/practitioners', [$c, 'storePractitioner']);
    Route::post('/booking-setup/practitioners/{id}', [$c, 'updatePractitioner'])->whereNumber('id');
});

// Phase 3: Today, all conversations, tasks, library (everyone); tags and library edits (owner or manager); settings (owner).
Route::middleware('staff')->prefix('admin')->group(function () {
    Route::get('/today', [\App\Http\Controllers\TodayController::class, 'index'])->name('today');
    Route::get('/conversations', [\App\Http\Controllers\ConversationsController::class, 'index'])->name('conversations');
    Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('tasks');
    Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store']);
    Route::post('/tasks/{id}/toggle', [\App\Http\Controllers\TaskController::class, 'toggle'])->whereNumber('id');
    Route::post('/tasks/{id}/delete', [\App\Http\Controllers\TaskController::class, 'destroy'])->whereNumber('id');
    Route::get('/library', [\App\Http\Controllers\LibraryController::class, 'index'])->name('library');
});

Route::middleware(['staff', 'manager'])->prefix('admin')->group(function () {
    $t = \App\Http\Controllers\TagController::class;
    Route::get('/tags', [$t, 'index'])->name('tags');
    Route::post('/tags', [$t, 'store']);
    Route::post('/tags/{id}', [$t, 'update'])->whereNumber('id');
    Route::post('/tags/{id}/merge', [$t, 'merge'])->whereNumber('id');
    Route::post('/tags/{id}/delete', [$t, 'destroy'])->whereNumber('id');

    $l = \App\Http\Controllers\LibraryController::class;
    Route::post('/library', [$l, 'store']);
    Route::post('/library/{id}', [$l, 'update'])->whereNumber('id');
    Route::post('/library/{id}/delete', [$l, 'destroy'])->whereNumber('id');

    $tp = \App\Http\Controllers\TemplateController::class;
    Route::get('/templates', [$tp, 'index'])->name('templates');
    Route::post('/templates', [$tp, 'store']);
    Route::post('/templates/sync', [$tp, 'sync']);
    Route::post('/templates/{id}/delete', [$tp, 'destroy'])->whereNumber('id');
});

Route::middleware(['staff', 'owner'])->prefix('admin')->group(function () {
    $ch = \App\Http\Controllers\ChannelController::class;
    Route::get('/channels', [$ch, 'index'])->name('channels');
    Route::post('/channels', [$ch, 'store']);
    Route::post('/channels/{id}', [$ch, 'update'])->whereNumber('id');
    Route::post('/channels/{id}/delete', [$ch, 'destroy'])->whereNumber('id');
    Route::post('/channels/{id}/check', [$ch, 'check'])->whereNumber('id');
    Route::post('/channels/{id}/subscribe', [$ch, 'subscribe'])->whereNumber('id');
    Route::post('/channels/{id}/test', [$ch, 'test'])->whereNumber('id');

    $c = \App\Http\Controllers\SettingsController::class;
    Route::get('/settings', [$c, 'index'])->name('settings');
    Route::post('/settings/profile', [$c, 'saveProfile']);
    Route::post('/settings/team', [$c, 'storeUser']);
    Route::post('/settings/team/{id}', [$c, 'updateUser'])->whereNumber('id');
});

// Meta (WhatsApp, Messenger, Instagram) calls this address. It must be reachable from the internet (see README, Step 4).
Route::get('/webhook/meta', [\App\Http\Controllers\MetaWebhookController::class, 'verify']);
Route::post('/webhook/meta', [\App\Http\Controllers\MetaWebhookController::class, 'receive'])->middleware('throttle:meta-webhook');
