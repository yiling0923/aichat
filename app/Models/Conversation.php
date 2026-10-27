<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToBusiness;

    public const MODE_BOT = 'bot';

    public const MODE_HUMAN = 'human';

    protected $fillable = [
        'customer_id',
        'channel',
        'token',
        'contact_name',
        'language',
        'mode',
        'status',
        'handover_reason',
        'handover_at',
        'opted_out_at',
        'last_message_at',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'handover_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_message_at' => 'datetime',
            'state' => 'array',
        ];
    }

    public static function startFor(string $channel = 'web'): self
    {
        return static::create([
            'channel' => $channel,
            'token' => Str::random(40),
            'mode' => self::MODE_BOT,
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isHandledByStaff(): bool
    {
        return $this->mode === self::MODE_HUMAN;
    }

    /**
     * Store a message in this conversation and bump its activity time.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function addMessage(string $direction, string $senderType, ?string $body, ?array $payload = null, bool $private = false): Message
    {
        $message = new Message([
            'direction' => $direction,
            'sender_type' => $senderType,
            'body' => $body,
            'payload' => $payload,
            'is_private' => $private,
        ]);
        $message->forceFill([
            'business_id' => $this->business_id,
            'conversation_id' => $this->id,
        ])->save();

        if (! $private) {
            $this->forceFill(['last_message_at' => now()])->save();
        }

        return $message;
    }

    public function handOverToStaff(string $reason): void
    {
        $this->forceFill([
            'mode' => self::MODE_HUMAN,
            'handover_reason' => $reason,
            'handover_at' => now(),
        ])->save();
    }

    public function giveBackToBot(): void
    {
        $this->forceFill(['mode' => self::MODE_BOT])->save();
    }
}
