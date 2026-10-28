<?php

namespace App\Ai;

use Anthropic\Client;
use RuntimeException;

/**
 * Claude through Anthropic's official PHP SDK. The business knowledge goes in the system prompt, the
 * conversation in the messages, and the answer comes back as JSON that follows a schema.
 */
class ClaudeProvider implements AiProvider
{
    public function name(): string
    {
        return 'claude';
    }

    public function complete(string $system, array $messages, array $schema, int $maxTokens): array
    {
        $key = (string) config('aichat.ai.api_key');

        if ($key === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set in .env.');
        }

        $model = (string) config('aichat.ai.model');
        $client = new Client(apiKey: $key);

        $message = $client->messages->create(
            model: $model,
            maxTokens: $maxTokens,
            system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            messages: $messages,
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('The AI declined to answer.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'text' => $text,
            'input_tokens' => (int) ($message->usage->inputTokens ?? 0),
            'output_tokens' => (int) ($message->usage->outputTokens ?? 0),
            'model' => $model,
        ];
    }
}
