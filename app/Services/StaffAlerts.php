<?php

namespace App\Services;

use App\Channels\MetaGraph;
use App\Mail\WaitingChatMail;
use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alerts that reach staff outside the browser: email to owners and managers, and a WhatsApp message to one number.
 * Both are switched on per business in Settings. A failure is logged and never breaks anything else.
 */
class StaffAlerts
{
    public function __construct(private MetaGraph $graph) {}

    public function waiting(Business $business, Conversation $conversation, int $minutes, bool $urgent): void
    {
        $name = Notifier::chatName($conversation);
        $url = route('inbox', ['c' => $conversation->id]);
        $settings = (array) $business->settings;

        if (! empty($settings['alert_email'])) {
            $this->email($business, $name, $minutes, $urgent, $url, $conversation->handover_reason);
        }

        if (! empty($settings['alert_whatsapp'])) {
            $this->whatsapp($business, $name, $minutes, ($urgent ? 'URGENT: ' : '').$name.' has waited '.$minutes.' minutes for a reply at '.$business->name.'. Open the Inbox to answer.');
        }
    }

    /** A test from the Settings page: email to the person pressing the button, and the WhatsApp number if set. @return array{email: bool, whatsapp: ?string} */
    public function test(Business $business, User $user): array
    {
        $result = ['email' => false, 'whatsapp' => null];

        try {
            \App\Support\Lang::as($user->getAttribute('locale'), fn () => Mail::to($user->email)->send(new WaitingChatMail($business, __('Test customer'), 12, false, route('inbox'), __('This is only a test.'))));
            $result['email'] = true;
        } catch (Throwable $e) {
            $this->quietly($e);
        }

        if (! empty($business->settings['alert_whatsapp'])) {
            $result['whatsapp'] = $this->whatsapp($business, 'Test customer', 12, 'This is a test alert from '.$business->name.'. Real alerts look like this when a customer waits for a reply.') ?? 'sent';
        }

        return $result;
    }

    /** Writing to the log can itself fail (for example a locked laravel.log on Windows): an alert problem must never break the page. */
    private function quietly(Throwable $e): void
    {
        try {
            report($e);
        } catch (Throwable) {
            // nothing more can be done
        }
    }

    private function email(Business $business, string $name, int $minutes, bool $urgent, string $url, ?string $reason): void
    {
        $to = User::query()->where('business_id', $business->id)->where('active', true)->whereIn('role', ['owner', 'manager'])->get()->filter(fn (User $u) => $u->email);

        foreach ($to as $user) {
            try {
                \App\Support\Lang::as($user->getAttribute('locale'), fn () => Mail::to($user->email)->send(new WaitingChatMail($business, $name, $minutes, $urgent, $url, $reason)));
            } catch (Throwable $e) {
                $this->quietly($e);
            }
        }
    }

    /** @return string|null an error text, or null when it was sent */
    private function whatsapp(Business $business, string $name, int $minutes, string $text): ?string
    {
        $settings = (array) $business->settings;
        $channel = Channel::query()->where('type', 'whatsapp')->where('active', true)->first();

        if ($channel === null) {
            return 'No WhatsApp channel is connected.';
        }

        $to = preg_replace('/\D+/', '', (string) $settings['alert_whatsapp']);
        $json = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to];
        $templateName = (string) ($settings['alert_whatsapp_template'] ?? '');
        $template = $templateName !== '' ? MessageTemplate::query()->where('channel_id', $channel->id)->where('name', $templateName)->where('status', 'APPROVED')->first() : null;

        // Meta only lets a business message someone first with an approved template. Without one, the text only arrives
        // if that number wrote to the business in the last 24 hours.
        if ($template !== null) {
            $params = [$name, (string) $minutes];
            $json += ['type' => 'template', 'template' => ['name' => $template->name, 'language' => ['code' => $template->language]]];
            if ($template->parameterCount() > 0) {
                $json['template']['components'] = [['type' => 'body', 'parameters' => array_map(fn ($i) => ['type' => 'text', 'text' => (string) ($params[$i] ?? '')], range(0, $template->parameterCount() - 1))]];
            }
        } else {
            $json += ['type' => 'text', 'text' => ['body' => mb_substr($text, 0, 1000)]];
        }

        try {
            $this->graph->orFail($this->graph->post($channel->external_id.'/messages', $channel->access_token, $json));

            return null;
        } catch (Throwable $e) {
            $this->quietly($e);

            return mb_substr($e->getMessage(), 0, 200);
        }
    }
}
