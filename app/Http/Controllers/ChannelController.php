<?php

namespace App\Http\Controllers;

use App\Channels\MetaGraph;
use App\Channels\WebhookRegistrar;
use App\Models\Channel;
use App\Models\MessageTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Owner only: connect WhatsApp, Messenger and Instagram (the "Meta setup" page).
 */
class ChannelController extends Controller
{
    public function index(Request $request): View
    {
        // Built from APP_URL (the public tunnel address), not from the address you are browsing on,
        // so it is right even when you manage the app from http://localhost.
        $url = rtrim((string) config('app.url'), '/').'/webhook/meta';

        return view('admin.channels', [
            'business' => $request->user()->business,
            'active' => 'channels',
            'channels' => Channel::query()->with('templates')->orderBy('id')->get(),
            'webhookUrl' => $url,
            'verifyToken' => (string) config('services.meta.verify_token'),
            'secretSet' => (string) config('services.meta.app_secret') !== '',
            'isLocal' => (bool) preg_match('#^https?://(localhost|127\.|192\.168\.|10\.)#', $url),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Channel::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'external_id' => ['required', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'waba_id' => ['nullable', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'access_token' => ['required', 'string', 'max:1000'],
        ]);

        if (! app(\App\Billing\Plans::class)->canAdd($request->user()->business, 'channels')) {
            return back()->withErrors(['type' => 'Your plan has reached its limit of connected channels. Upgrade under Plan & billing.'])->withInput();
        }

        // Ids are unique across all businesses, so nobody can take over another business's number or page.
        if (Channel::query()->withoutGlobalScopes()->where('type', $data['type'])->where('external_id', $data['external_id'])->exists()) {
            return back()->withErrors(['external_id' => 'This '.Channel::TYPES[$data['type']].' account is already connected.'])->withInput();
        }

        Channel::create($data + ['active' => true]);

        return back()->with('status', 'Channel added. Next: copy the webhook address into Meta, then press "Check connection".');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'waba_id' => ['nullable', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'access_token' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
            'reminder_template' => ['nullable', 'string', 'max:80'],
        ]);

        $channel->fill(['name' => $data['name'], 'waba_id' => $data['waba_id'] ?? null, 'active' => (bool) $data['active']]);
        if (! empty($data['access_token'])) {
            $channel->access_token = $data['access_token'];
        }
        $settings = (array) $channel->settings;
        $settings['reminder_template'] = $data['reminder_template'] ?: null;
        $channel->settings = $settings;
        $channel->save();

        return back()->with('status', 'Channel saved.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Channel::query()->findOrFail($id)->delete();

        return back()->with('status', 'Channel removed. Old chats stay in the inbox but can no longer be answered there.');
    }

    /** Asks Meta who this token belongs to, to prove the id and token work. */
    public function check(int $id, MetaGraph $graph): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);

        try {
            $fields = $channel->type === 'whatsapp' ? 'display_phone_number,verified_name' : 'name';
            $info = $graph->orFail($graph->get($channel->external_id, $channel->access_token, ['fields' => $fields]))->json();
            $channel->forceFill(['last_error' => null])->save();
            $who = $info['display_phone_number'] ?? $info['name'] ?? $channel->external_id;

            return back()->with('status', 'Connected to '.$who.'.');
        } catch (Throwable $e) {
            $channel->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->withErrors(['channel' => 'Meta says: '.$e->getMessage()]);
        }
    }

    /** Saves the Callback URL and Verify token in the Meta app. */
    public function registerWebhook(WebhookRegistrar $registrar): RedirectResponse
    {
        // Only this business's kinds of channel are named in the message, but the webhook is app-wide.
        try {
            $done = $registrar->register(Channel::query()->pluck('type')->unique()->all());
        } catch (Throwable $e) {
            return back()->withErrors(['channel' => $e->getMessage()]);
        }

        return back()->with('status', 'Webhook saved in Meta for '.implode(', ', $done).'. Now press "Subscribe to messages" on the channel and send a test.');
    }

    /** Tells Meta to send this account's messages to our webhook. */
    public function subscribe(int $id, MetaGraph $graph): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);
        $target = $channel->type === 'messenger' ? $channel->external_id : $channel->waba_id;

        if (! $target) {
            return back()->withErrors(['channel' => $channel->type === 'instagram'
                ? 'Add the linked Facebook Page ID first.' : 'Add the WhatsApp Business Account ID first.']);
        }

        try {
            $graph->orFail($graph->post($target.'/subscribed_apps', $channel->access_token, $channel->type === 'whatsapp'
                ? [] : ['subscribed_fields' => ['messages', 'messaging_postbacks']]));

            return back()->with('status', 'Subscribed. New customer messages will now reach this app.');
        } catch (Throwable $e) {
            return back()->withErrors(['channel' => 'Meta says: '.$e->getMessage()]);
        }
    }

    /** WhatsApp test numbers can send the built-in "hello_world" template to a number you added as a recipient. */
    public function test(Request $request, int $id, MetaGraph $graph): RedirectResponse
    {
        $channel = Channel::query()->where('type', 'whatsapp')->findOrFail($id);
        $data = $request->validate(['to' => ['required', 'string', 'max:30']]);
        $to = preg_replace('/\D+/', '', $data['to']);

        try {
            $graph->orFail($graph->post($channel->external_id.'/messages', $channel->access_token, [
                'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']],
            ]));

            return back()->with('status', 'Test message sent to '.$to.'. Check that phone.');
        } catch (Throwable $e) {
            return back()->withErrors(['channel' => 'Meta says: '.$e->getMessage()]);
        }
    }
}
