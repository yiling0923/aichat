<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['channel_id', 'name', 'language', 'category', 'body', 'status', 'meta_id', 'last_error', 'last_synced_at'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isApproved(): bool
    {
        return strtoupper($this->status) === 'APPROVED';
    }

    /** How many {{1}} {{2}} placeholders the body has. */
    public function parameterCount(): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $this->body, $m);

        return $m[1] === [] ? 0 : (int) max($m[1]);
    }
}
