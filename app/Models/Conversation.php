<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToBusiness;

    public const MODE_BOT = 'bot';

    public const MODE_HUMAN = 'human';

    protected $fillable = [
        'customer_id',
        'channel_id',
        'external_id',
        'last_inbound_at',
        'channel',
        'token',
        'contact_name',
        'language',
        'mode',
        'status',
        'assigned_to',
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
            'last_inbound_at' => 'datetime',
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

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id')->withoutGlobalScopes();
    }

    /**
     * Customer-service window: on WhatsApp, Messenger and Instagram free-form replies are only allowed
     * for 24 hours after the customer's last message. Website chat has no window.
     */
    public function windowOpen(): bool
    {
        if (! $this->channel_id) {
            return true;
        }

        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
    public function addMessage(string $direction, string $senderType, ?string $body, ?array $payload = null, bool $private = false, bool $deliver = true): Message
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

        // Replies on WhatsApp, Messenger and Instagram are sent out as soon as they are saved.
        if ($deliver && $direction === 'out' && ! $private && $this->channel_id && in_array($senderType, ['bot', 'staff'], true)) {
            app(\App\Channels\ChannelSender::class)->deliver($this, $message);
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
