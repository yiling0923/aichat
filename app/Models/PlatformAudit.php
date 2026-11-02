<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line in the platform owner's activity log. Not tied to one business's data, so it has no business scope. */
class PlatformAudit extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'business_id', 'action', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(string $action, ?int $businessId = null, ?string $details = null): void
    {
        static::query()->create(['user_id' => auth()->id(), 'business_id' => $businessId, 'action' => $action, 'details' => $details !== null ? mb_substr($details, 0, 300) : null, 'created_at' => now()]);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
