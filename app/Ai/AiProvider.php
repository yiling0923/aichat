<?php

namespace App\Ai;

/**
 * Something that can answer a prompt. The real one calls Claude; the fake one is for trying the
 * screens without internet or cost, and for automated tests.
 */
interface AiProvider
{
    /** "claude" or "fake" */
    public function name(): string;

    /**
     * @param  list<array{role: string, content: string}>  $messages  alternating user / assistant turns, starting with the user
     * @param  array<string, mixed>  $schema  JSON schema the answer must follow
     * @return array{text: string, input_tokens: int, output_tokens: int, model: ?string} text is a JSON document
     */
    public function complete(string $system, array $messages, array $schema, int $maxTokens): array;
}
