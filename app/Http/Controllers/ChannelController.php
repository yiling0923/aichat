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
            'appIdSet' => (string) config('services.meta.app_id') !== '',
            'verifySet' => (string) config('services.meta.verify_token') !== '',
            'chatUrl' => route('chat.show', $request->user()->business->slug),
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
            return back()->withErrors(['type' => __('Your plan has reached its limit of connected channels. Upgrade under Plan & billing.')])->withInput();
        }

        // Ids are unique across all businesses, so nobody can take over another business's number or page.
        if (Channel::query()->withoutGlobalScopes()->where('type', $data['type'])->where('external_id', $data['external_id'])->exists()) {
            return back()->withErrors(['external_id' => __('This :type account is already connected.', ['type' => Channel::TYPES[$data['type']]])])->withInput();
        }

        Channel::create($data + ['active' => true]);

        return back()->with('status', __('Channel added. Next: copy the webhook address into Meta, then press "Check connection".'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'external_id' => ['nullable', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'waba_id' => ['nullable', 'string', 'max:100', 'regex:/^[0-9A-Za-z_\-]+$/'],
            'access_token' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
            'reminder_template' => ['nullable', 'string', 'max:80'],
        ]);

        // The ID Meta puts in each message (Phone number ID, Page ID). Fixing a wrong one here saves removing and adding the channel again.
        if (! empty($data['external_id']) && $data['external_id'] !== $channel->external_id) {
            if (Channel::query()->withoutGlobalScopes()->where('type', $channel->type)->where('external_id', $data['external_id'])->where('id', '!=', $channel->id)->exists()) {
                return back()->withErrors(['external_id' => __('This :type account is already connected.', ['type' => Channel::TYPES[$channel->type]])]);
            }
            $channel->external_id = $data['external_id'];
        }

        $channel->fill(['name' => $data['name'], 'waba_id' => $data['waba_id'] ?? null, 'active' => (bool) $data['active']]);
        if (! empty($data['access_token'])) {
            $channel->access_token = $data['access_token'];
        }
        $settings = (array) $channel->settings;
        if (array_key_exists('reminder_template', $data)) {      // only the WhatsApp form has it
            $settings['reminder_template'] = $data['reminder_template'] ?: null;
        }
        $channel->settings = $settings;
        $channel->save();

        return back()->with('status', __('Channel saved.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        Channel::query()->findOrFail($id)->delete();

        return back()->with('status', __('Channel removed. Old chats stay in the inbox but can no longer be answered there.'));
    }

    /** Asks Meta who this token belongs to, to prove the id and token work. */
    public function check(int $id, MetaGraph $graph): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);

        try {
            if ($channel->type === 'whatsapp') {
                $info = $graph->orFail($graph->get($channel->external_id, $channel->access_token, ['fields' => 'display_phone_number,verified_name']))->json();
            } else {
                // A Page token answers "who am I" without any extra permission (reading the Page by its ID needs pages_read_engagement).
                $info = $graph->orFail($graph->get('me', $channel->access_token, ['fields' => 'id,name']))->json();

                if (! empty($info['id']) && (string) $info['id'] !== (string) $channel->external_id && $channel->type === 'messenger') {
                    return back()->withErrors(['channel' => __('This token belongs to the Page ":name" (ID :id), but this channel uses the ID :ext. Messages for the other ID are ignored. Put :id in the Page ID box, or use the token of the right Page.', ['name' => $info['name'] ?? '', 'id' => $info['id'], 'ext' => $channel->external_id])]);
                }
            }
            $channel->forceFill(['last_error' => null])->save();
            $who = $info['display_phone_number'] ?? $info['name'] ?? $channel->external_id;

            return back()->with('status', __('Connected to :who.', ['who' => $who]));
        } catch (Throwable $e) {
            $channel->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->withErrors(['channel' => __('Meta says: :why', ['why' => $e->getMessage()])]);
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

        return back()->with('status', __('Webhook saved in Meta for :list. Now press "Subscribe to messages" on the channel and send a test.', ['list' => implode(', ', $done)]));
    }

    /** Tells Meta to send this account's messages to our webhook. */
    public function subscribe(int $id, MetaGraph $graph): RedirectResponse
    {
        $channel = Channel::query()->findOrFail($id);
        $target = $channel->type === 'messenger' ? $channel->external_id : $channel->waba_id;

        if (! $target) {
            return back()->withErrors(['channel' => $channel->type === 'instagram'
                ? __('Add the linked Facebook Page ID first.') : __('Add the WhatsApp Business Account ID first.')]);
        }

        try {
            $graph->orFail($graph->post($target.'/subscribed_apps', $channel->access_token, $channel->type === 'whatsapp'
                ? [] : ['subscribed_fields' => ['messages', 'messaging_postbacks']]));

            return back()->with('status', __('Subscribed. New customer messages will now reach this app.'));
        } catch (Throwable $e) {
            $hint = str_contains($e->getMessage(), 'pages_manage_metadata') || str_contains($e->getMessage(), 'pages_read_engagement') ? ' '.__('This token cannot subscribe the Page itself. Do it in Meta instead: Messenger API settings, next to your Page press "Add subscriptions" and tick messages.') : '';

            return back()->withErrors(['channel' => __('Meta says: :why', ['why' => $e->getMessage()]).$hint]);
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

            return back()->with('status', __('Test message sent to :to. Check that phone.', ['to' => $to]));
        } catch (Throwable $e) {
            return back()->withErrors(['channel' => __('Meta says: :why', ['why' => $e->getMessage()])]);
        }
    }
}
