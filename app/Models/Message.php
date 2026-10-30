<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'conversation_id',
        'external_id',
        'direction',
        'sender_type',
        'body',
        'payload',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_private' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Shape used by the chat widget and the inbox.
     *
     * @return array<string, mixed>
     */
    /** What the Inbox needs to show a photo, voice note, file or location (the file itself is opened through the media address). */
    private function mediaArray(): ?array
    {
        $m = (array) ($this->payload['media'] ?? []);

        if ($m === []) {
            return null;
        }

        return [
            'type' => $m['type'] ?? 'other',
            'caption' => $m['caption'] ?? '',
            'filename' => $m['filename'] ?? '',
            'file' => ($m['type'] ?? '') !== 'location' && (! empty($m['id']) || ! empty($m['url']) || ! empty($m['path'])),
            'lat' => $m['lat'] ?? null,
            'lng' => $m['lng'] ?? null,
            'place' => trim(($m['name'] ?? '').' '.($m['address'] ?? '')),
            'url' => ! empty($m['token']) && $this->direction === 'out' ? url('/f/'.$this->id.'/'.$m['token']) : null,
        ];
    }

    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->sender_type,
            'text' => $this->body,
            'options' => $this->payload['options'] ?? [],
            'private' => $this->is_private,
            'time' => $this->created_at?->toIso8601String(),
            'ai' => $this->sender_type === 'ai' ? ['status' => $this->payload['status'] ?? 'pending', 'intent' => $this->payload['intent'] ?? null, 'mood' => $this->payload['mood'] ?? null] : null,
            'media' => $this->mediaArray(),
            'delivery' => $this->payload['delivery'] ?? null,
            'delivery_error' => $this->payload['delivery_error'] ?? null,
        ];
    }
}
