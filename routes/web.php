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
    Route::post('/api/conversations/{id}/ai/{messageId}/send', [InboxController::class, 'aiSend'])->whereNumber(['id', 'messageId']);
    Route::post('/api/conversations/{id}/ai/{messageId}/dismiss', [InboxController::class, 'aiDismiss'])->whereNumber(['id', 'messageId']);
    Route::post('/api/conversations/{id}/summarize', [InboxController::class, 'summarize'])->whereNumber('id');
    Route::post('/api/conversations/{id}/tag', [InboxController::class, 'tag'])->whereNumber('id');
    Route::post('/api/conversations/{id}/assign', [InboxController::class, 'assign'])->whereNumber('id');
    Route::post('/api/conversations/{id}/template', [\App\Http\Controllers\TemplateController::class, 'send'])->whereNumber('id');
    Route::get('/api/search', \App\Http\Controllers\SearchController::class)->middleware('throttle:120,1');
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
    Route::post('/schedule/{id}/move', [\App\Http\Controllers\ScheduleController::class, 'move'])->whereNumber('id');
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
    Route::get('/manage', [\App\Http\Controllers\ManageController::class, 'index'])->name('manage');
    Route::get('/customers/{id}/export', [\App\Http\Controllers\CustomerController::class, 'export'])->whereNumber('id');
    Route::post('/customers/{id}/erase', [\App\Http\Controllers\CustomerController::class, 'erase'])->whereNumber('id');

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

    $ai = \App\Http\Controllers\AiSettingsController::class;
    Route::get('/ai', [$ai, 'index'])->name('ai');
    Route::post('/ai', [$ai, 'save']);
    Route::post('/ai/preview', [$ai, 'preview'])->middleware('throttle:30,1');
    Route::post('/ai/knowledge', [$ai, 'storeEntry']);
    Route::post('/ai/knowledge/{id}', [$ai, 'updateEntry'])->whereNumber('id');
    Route::post('/ai/knowledge/{id}/delete', [$ai, 'destroyEntry'])->whereNumber('id');

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
    Route::post('/channels/webhook', [$ch, 'registerWebhook']);
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

// Phase 5: automations, broadcasts (owner or manager); orders and reports (everyone).
Route::middleware('staff')->prefix('admin')->group(function () {
    $o = \App\Http\Controllers\OrderController::class;
    Route::get('/orders', [$o, 'index'])->name('orders');
    Route::post('/orders', [$o, 'store']);
    Route::post('/orders/{id}', [$o, 'update'])->whereNumber('id');
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports');
    Route::get('/reports/export', [\App\Http\Controllers\ReportsController::class, 'export'])->name('reports.export');
});

Route::middleware(['staff', 'manager'])->prefix('admin')->group(function () {
    $a = \App\Http\Controllers\AutomationController::class;
    Route::get('/automations', [$a, 'index'])->name('automations');
    Route::get('/automations/activity', [$a, 'activity'])->name('automations.activity');
    Route::post('/automations/run', [$a, 'runNow']);
    Route::post('/automations/{key}', [$a, 'update'])->where('key', '[a-z_]+');

    $b = \App\Http\Controllers\BroadcastController::class;
    Route::get('/broadcasts', [$b, 'index'])->name('broadcasts');
    Route::post('/broadcasts', [$b, 'store']);
    Route::get('/broadcasts/{id}', [$b, 'show'])->whereNumber('id')->name('broadcasts.show');
    Route::post('/broadcasts/{id}/send', [$b, 'send'])->whereNumber('id');
    Route::post('/broadcasts/{id}/delete', [$b, 'destroy'])->whereNumber('id');
});

// Phase 6: sign-up, email confirmation, getting-started guide, plans, legal pages and the platform owner console.
Route::get('/signup', [\App\Http\Controllers\SignupController::class, 'show'])->name('signup');
Route::post('/signup', [\App\Http\Controllers\SignupController::class, 'store'])->middleware('throttle:signup');
Route::get('/email/verify', [\App\Http\Controllers\SignupController::class, 'notice'])->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\SignupController::class, 'verify'])->whereNumber('id')->middleware('throttle:20,1')->name('verification.verify');
Route::post('/email/resend', [\App\Http\Controllers\SignupController::class, 'resend'])->middleware(['auth', 'throttle:3,10'])->name('verification.resend');
Route::get('/terms', [\App\Http\Controllers\LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', [\App\Http\Controllers\LegalController::class, 'privacy'])->name('privacy');

Route::middleware(['staff', 'owner'])->prefix('admin')->group(function () {
    $s = \App\Http\Controllers\SetupController::class;
    Route::get('/setup', [$s, 'index'])->name('setup');
    Route::post('/setup/draft', [$s, 'draft'])->middleware('throttle:10,1');
    Route::post('/setup/approve', [$s, 'approve']);
    Route::post('/setup/discard', [$s, 'discard']);
    Route::post('/setup/go-live', [$s, 'goLive']);

    $b = \App\Http\Controllers\BillingController::class;
    Route::get('/billing', [$b, 'index'])->name('billing');
    Route::post('/billing/subscribe', [$b, 'subscribe']);
    Route::post('/billing/cancel', [$b, 'cancel']);
});

Route::middleware(['auth', 'platform'])->prefix('platform')->group(function () {
    $p = \App\Http\Controllers\PlatformController::class;
    Route::get('/', [$p, 'index'])->name('platform.index');
    Route::post('/invites', [$p, 'invite']);
    Route::post('/businesses/{id}/pause', [$p, 'pause'])->whereNumber('id');
    Route::post('/businesses/{id}/resume', [$p, 'resume'])->whereNumber('id');
});

// Phase 7: monitoring, password reset.
Route::post('/webhook/stripe', \App\Http\Controllers\StripeWebhookController::class)->middleware('throttle:meta-webhook');
Route::get('/health', \App\Http\Controllers\HealthController::class)->middleware('throttle:60,1');

Route::middleware('guest')->group(function () {
    $r = \App\Http\Controllers\PasswordResetController::class;
    Route::get('/forgot-password', [$r, 'request'])->name('password.request');
    Route::post('/forgot-password', [$r, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [$r, 'form'])->name('password.reset');
    Route::post('/reset-password', [$r, 'update'])->middleware('throttle:10,1')->name('password.update');
});
