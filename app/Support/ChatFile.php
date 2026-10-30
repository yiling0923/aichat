<?php

namespace App\Support;

/** Which files can be sent to a customer (the same list for the Inbox attach button and for menu steps). */
class ChatFile
{
    public const MAX_KB = 16384;

    public const REFUSED = 'This file type cannot be sent. Use a photo (JPG or PNG), video (MP4), voice note, PDF or Office file.';

    /** image, video, audio or document; null when the type cannot be sent. */
    public static function type(string $mime): ?string
    {
        $mime = strtolower($mime);

        return match (true) {
            in_array($mime, ['image/jpeg', 'image/png'], true) => 'image',
            in_array($mime, ['video/mp4', 'video/3gpp'], true) => 'video',
            in_array($mime, ['audio/ogg', 'audio/mpeg', 'audio/aac', 'audio/amr'], true) => 'audio',
            in_array($mime, ['application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'image/webp', 'image/gif'], true) => 'document',
            default => null,
        };
    }
}
