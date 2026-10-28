<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** An invitation code for the invite-only beta. Not tenant-scoped: the platform owner manages these. */
class Invite extends Model
{
    protected $fillable = ['code', 'email', 'note', 'used_at', 'business_id'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public static function generate(?string $email = null, ?string $note = null): self
    {
        return self::create(['code' => Str::upper(Str::random(10)), 'email' => $email ? Str::lower($email) : null, 'note' => $note]);
    }
}
