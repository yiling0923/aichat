<?php

namespace App\Channels;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * WhatsApp message templates: create them at Meta, refresh their approval status, and send one
 * when the 24-hour window is closed (the only kind of message WhatsApp then allows).
 */
class Templates
{
    public function __construct(private MetaGraph $graph) {}

    /** Ask Meta to review a new template. The status stays PENDING until Meta decides. */
    public function create(Channel $channel, string $name, string $language, string $category, string $body): MessageTemplate
    {
        if (! $channel->waba_id) {
            throw new RuntimeException('Add the WhatsApp Business Account ID to this channel first.');
        }

        $template = MessageTemplate::query()->updateOrCreate(
            ['channel_id' => $channel->id, 'name' => $name, 'language' => $language],
            ['category' => $category, 'body' => $body, 'status' => 'draft', 'last_error' => null],
        );

        $component = ['type' => 'BODY', 'text' => $body];
        if (($count = $template->parameterCount()) > 0) {
            $component['example'] = ['body_text' => [array_map(fn ($i) => 'Example '.$i, range(1, $count))]];
        }

        try {
            $response = $this->graph->orFail($this->graph->post($channel->waba_id.'/message_templates', $channel->access_token, [
                'name' => $name, 'language' => $language, 'category' => $category, 'components' => [$component],
            ]));
            $template->update(['meta_id' => $response->json('id'), 'status' => strtoupper((string) ($response->json('status') ?? 'PENDING')), 'last_synced_at' => now()]);
        } catch (Throwable $e) {
            $template->update(['last_error' => $e->getMessage()]);
            throw $e;
        }

        return $template;
    }

    /** Pull the latest approval status (and any templates made in Meta's own tools) into the app. */
    public function sync(Channel $channel): int
    {
        if (! $channel->waba_id) {
            throw new RuntimeException('Add the WhatsApp Business Account ID to this channel first.');
        }

        $response = $this->graph->orFail($this->graph->get($channel->waba_id.'/message_templates', $channel->access_token, [
            'fields' => 'name,language,status,category,id,components', 'limit' => 100,
        ]));

        $count = 0;
        foreach ((array) $response->json('data', []) as $row) {
            $body = collect($row['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? null;
            $existing = MessageTemplate::query()->where('channel_id', $channel->id)->where('name', $row['name'])->where('language', $row['language'])->first();

            if ($existing === null && $body === null) {
                continue; // nothing we can send as text
            }

            MessageTemplate::query()->updateOrCreate(
                ['channel_id' => $channel->id, 'name' => $row['name'], 'language' => $row['language']],
                array_filter([
                    'category' => $row['category'] ?? 'UTILITY',
                    'status' => strtoupper((string) ($row['status'] ?? 'PENDING')),
                    'meta_id' => $row['id'] ?? null,
                    'body' => $body,
                    'last_synced_at' => now(),
                ], fn ($v) => $v !== null),
            );
            $count++;
        }

        return $count;
    }

    /**
     * Send an approved template to a customer and save it in the chat.
     *
     * @param  list<string>  $params  values for {{1}}, {{2}} ...
     */
    public function send(Conversation $conversation, MessageTemplate $template, array $params, string $senderType = 'staff'): Message
    {
        $channel = $conversation->channelAccount;

        if ($channel === null || $channel->type !== 'whatsapp' || ! $conversation->external_id) {
            throw new RuntimeException('Templates can only be sent on WhatsApp chats.');
        }
        if (! $template->isApproved()) {
            throw new RuntimeException('This template is not approved yet.');
        }

        $text = preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $params[(int) $m[1] - 1] ?? '', $template->body) ?? $template->body;
        $message = $conversation->addMessage('out', $senderType, $text, ['template' => $template->name], false, false);

        $json = [
            'messaging_product' => 'whatsapp', 'to' => $conversation->external_id, 'type' => 'template',
            'template' => ['name' => $template->name, 'language' => ['code' => $template->language]],
        ];
        if ($template->parameterCount() > 0) {
            $json['template']['components'] = [['type' => 'body', 'parameters' => array_map(
                fn ($i) => ['type' => 'text', 'text' => (string) ($params[$i] ?? '')],
                range(0, $template->parameterCount() - 1),
            )]];
        }

        $payload = (array) $message->payload;
        try {
            $response = $this->graph->orFail($this->graph->post($channel->external_id.'/messages', $channel->access_token, $json));
            $payload['delivery'] = 'sent';
            $message->forceFill(['payload' => $payload, 'external_id' => $response->json('messages.0.id')])->save();
        } catch (Throwable $e) {
            Log::warning('Template send failed', ['error' => $e->getMessage()]);
            $payload['delivery'] = 'failed';
            $payload['delivery_error'] = $e->getMessage();
            $message->forceFill(['payload' => $payload])->save();
        }

        return $message;
    }
}
