<?php

namespace App\Marketing;

use App\Bot\Strings;
use App\Bot\Text;
use App\Channels\Templates;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\MessageTemplate;
use Throwable;

/**
 * Sends a message the customer did not just ask for (survey, follow-up, broadcast).
 * Rules: only people who agreed to be contacted and did not opt out; on WhatsApp, Messenger and Instagram
 * only inside the 24-hour window, or with an approved template on WhatsApp.
 */
class Outreach
{
    public function __construct(private Templates $templates) {}

    /**
     * @param  array<string, string>  $message  {en, zh}
     * @param  array<string, mixed>  $values  placeholders such as name, service, business, review_url
     * @param  list<array{id: string, label: string}>  $options
     * @param  list<string>  $templateParams  values for {{1}}, {{2}} of the WhatsApp template
     * @return array{status: string, reason: ?string, conversation: ?Conversation}
     */
    public function send(Customer $customer, array $message, array $values, array $options = [], ?string $templateName = null, array $templateParams = [], bool $skipIfStaffHandling = false): array
    {
        if ($customer->opted_out_at !== null) {
            return $this->result('skipped', 'opted out');
        }
        if ($customer->consented_at === null) {
            return $this->result('skipped', 'no consent');
        }

        $conversation = Conversation::query()->where('customer_id', $customer->id)->where('channel', '!=', 'preview')
            ->orderByDesc('last_message_at')->first();

        if ($conversation === null) {
            return $this->result('skipped', 'no conversation');
        }
        if ($skipIfStaffHandling && $conversation->isHandledByStaff()) {
            return $this->result('skipped', 'staff are handling the chat', $conversation);
        }

        $language = $conversation->language ?? $customer->language ?? 'en';
        $text = Text::fill((string) ($message[$language] ?? $message['en'] ?? reset($message) ?: ''), $values);

        if ($text === '' && $templateName === null) {
            return $this->result('skipped', 'empty message', $conversation);
        }

        try {
            if (! $conversation->windowOpen()) {
                $template = $templateName
                    ? MessageTemplate::query()->where('channel_id', $conversation->channel_id)->where('name', $templateName)->where('status', 'APPROVED')->first()
                    : null;

                if ($template === null) {
                    return $this->result('skipped', 'the 24-hour window is closed and no approved template is set', $conversation);
                }

                $sent = $this->templates->send($conversation, $template, $templateParams, 'bot');

                return $this->outcome($sent->payload['delivery'] ?? null, $sent->payload['delivery_error'] ?? null, $conversation);
            }

            $payload = $options === [] ? null : ['options' => $options];
            $sent = $conversation->addMessage('out', 'bot', $text, $payload);

            return $this->outcome($sent->payload['delivery'] ?? 'sent', $sent->payload['delivery_error'] ?? null, $conversation);
        } catch (Throwable $e) {
            report($e);

            return $this->result('failed', mb_substr($e->getMessage(), 0, 120), $conversation);
        }
    }

    /** @return list<array{id: string, label: string}> */
    public static function bookNowButton(string $language): array
    {
        return [['id' => '__book', 'label' => Strings::get('opt_book_now', $language)]];
    }

    /** @return list<array{id: string, label: string}> */
    public static function ratingButtons(string $language): array
    {
        return array_map(fn (int $n) => ['id' => 'rate_'.$n, 'label' => Strings::get('rate_'.$n, $language)], [5, 4, 3, 2, 1]);
    }

    private function outcome(?string $delivery, ?string $error, Conversation $conversation): array
    {
        return $delivery === 'failed'
            ? $this->result('failed', mb_substr((string) $error, 0, 120), $conversation)
            : $this->result('sent', null, $conversation);
    }

    /** @return array{status: string, reason: ?string, conversation: ?Conversation} */
    private function result(string $status, ?string $reason, ?Conversation $conversation = null): array
    {
        return ['status' => $status, 'reason' => $reason, 'conversation' => $conversation];
    }
}
