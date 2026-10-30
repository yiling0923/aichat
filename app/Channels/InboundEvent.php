<?php

namespace App\Channels;

/** One customer message, in the same shape for every channel. */
class InboundEvent
{
    public function __construct(
        public string $type,         // whatsapp, messenger, instagram
        public string $channelKey,   // phone number id / page id / instagram account id
        public string $from,         // the customer's id on that channel
        public string $messageId,
        public string $text,
        public ?string $optionId,    // set when the customer tapped a button, list row or quick reply
        public ?string $name,
        public ?int $timestamp,
        public ?array $media = null,  // a photo, voice note, file or location: ['type', 'id'|'url', 'mime', 'caption', 'filename', 'lat', 'lng', 'name', 'address']
    ) {}

    /** The short words shown in the Inbox list for a message that is not text. */
    public static function mediaLabel(?string $type): string
    {
        return match ($type) {
            'image' => '📷 Photo',
            'video' => '🎬 Video',
            'audio' => '🎤 Voice message',
            'document' => '📄 File',
            'location' => '📍 Location',
            'sticker' => 'Sticker',
            default => 'Attachment',
        };
    }
}
