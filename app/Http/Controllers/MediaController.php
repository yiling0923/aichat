<?php

namespace App\Http\Controllers;

use App\Channels\MetaGraph;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Opens a photo, voice note, video or file from a chat for the signed-in team. A customer's file is fetched from Meta once,
 * kept on this server, and only ever shown to staff of the same business.
 */
class MediaController extends Controller
{
    private const MAX_BYTES = 25 * 1024 * 1024;

    /** Only these are shown inside the page. Anything else (a customer can send any file) is offered as a download. */
    private const INLINE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/amr', 'audio/wav', 'audio/webm', 'video/mp4', 'video/3gpp', 'video/webm', 'application/pdf'];

    public function show(Request $request, int $id, MetaGraph $graph): Response
    {
        $message = Message::query()->findOrFail($id);
        $media = (array) ($message->payload['media'] ?? []);

        abort_if($media === [] || ($media['type'] ?? '') === 'location', 404);

        $disk = Storage::disk('local');
        $path = (string) ($media['path'] ?? '');

        if ($path === '' || ! $disk->exists($path)) {
            try {
                [$bytes, $mime] = $this->fetch($message, $media, $graph);
            } catch (Throwable $e) {
                abort(502, 'The file could not be downloaded from the channel. It may have expired.');
            }

            $path = 'chat-media/'.$message->business_id.'/'.$message->id;
            $disk->put($path, $bytes);
            $payload = (array) $message->payload;
            $payload['media'] = array_merge($media, ['path' => $path, 'mime' => $mime ?: ($media['mime'] ?? '')]);
            $message->forceFill(['payload' => $payload])->save();
            $media = $payload['media'];
        }

        $mime = strtolower(trim(explode(';', (string) ($media['mime'] ?? ''))[0]));
        $inline = in_array($mime, self::INLINE, true);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($media['filename'] ?? '')) ?: ('file-'.$message->id.$this->extension($mime));

        return response($disk->get($path), 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** @return array{0: string, 1: string} bytes and mime type */
    private function fetch(Message $message, array $media, MetaGraph $graph): array
    {
        $channel = $message->conversation?->channelAccount;
        $token = (string) $channel?->access_token;

        if (! empty($media['id'])) {
            $info = $graph->orFail($graph->get((string) $media['id'], $token))->json();
            $url = (string) ($info['url'] ?? '');
            $mime = (string) ($info['mime_type'] ?? ($media['mime'] ?? ''));
            $response = Http::timeout(30)->withToken($token)->get($url);
        } else {
            $url = (string) ($media['url'] ?? '');
            $mime = (string) ($media['mime'] ?? '');
            abort_unless(str_starts_with($url, 'https://'), 404);
            $response = Http::timeout(30)->get($url);
            $mime = $mime ?: (string) $response->header('Content-Type');
        }

        if ($response->failed() || strlen($response->body()) > self::MAX_BYTES) {
            throw new \RuntimeException('download failed');
        }

        return [$response->body(), $mime];
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif', 'application/pdf' => '.pdf',
            'audio/ogg' => '.ogg', 'audio/mpeg' => '.mp3', 'video/mp4' => '.mp4', default => '',
        };
    }
}
