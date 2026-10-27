<?php

namespace App\Channels;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Meta's Graph API. Every call needs the channel's access token.
 */
class MetaGraph
{
    public function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version').'/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $json */
    public function post(string $path, string $token, array $json): Response
    {
        return Http::timeout(10)->withToken($token)->acceptJson()->post($this->url($path), $json);
    }

    /** @param array<string, mixed> $query */
    public function get(string $path, string $token, array $query = []): Response
    {
        return Http::timeout(10)->withToken($token)->acceptJson()->get($this->url($path), $query);
    }

    /** Turn a failed response into a short message staff can read. */
    public function errorText(Response $response): string
    {
        $error = $response->json('error') ?? [];
        $message = $error['error_user_msg'] ?? $error['message'] ?? ('HTTP '.$response->status());

        return mb_substr(trim((string) $message), 0, 300);
    }

    /** @throws RuntimeException */
    public function orFail(Response $response): Response
    {
        if ($response->failed()) {
            throw new RuntimeException($this->errorText($response));
        }

        return $response;
    }
}
