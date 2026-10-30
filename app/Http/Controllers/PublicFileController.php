<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a file the team sent to a customer. Meta (and the website chat) fetch it by an address that contains a long random
 * code, so only the customer who was sent the link can open it. Nothing else on the server is reachable this way.
 */
class PublicFileController extends Controller
{
    private const INLINE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/amr', 'video/mp4', 'video/3gpp', 'application/pdf'];

    public function show(int $id, string $token): Response
    {
        $message = Message::query()->withoutGlobalScopes()->where('direction', 'out')->find($id);
        $media = (array) ($message?->payload['media'] ?? []);

        abort_if($message === null || empty($media['token']) || empty($media['path']) || ! hash_equals((string) $media['token'], $token), 404);
        abort_unless(Storage::disk('local')->exists($media['path']), 404);

        $mime = strtolower((string) ($media['mime'] ?? ''));
        $inline = in_array($mime, self::INLINE, true);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($media['filename'] ?? '')) ?: 'file-'.$message->id;

        return response(Storage::disk('local')->get($media['path']), 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
