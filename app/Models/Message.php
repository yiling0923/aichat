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
    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->sender_type,
            'text' => $this->body,
            'options' => $this->payload['options'] ?? [],
            'private' => $this->is_private,
            'time' => $this->created_at?->toIso8601String(),
            'delivery' => $this->payload['delivery'] ?? null,
            'delivery_error' => $this->payload['delivery_error'] ?? null,
        ];
    }
}
