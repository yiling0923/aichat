<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use BelongsToBusiness;

    public const TYPES = ['whatsapp' => 'WhatsApp', 'messenger' => 'Messenger', 'instagram' => 'Instagram'];

    protected $fillable = ['type', 'name', 'external_id', 'waba_id', 'access_token', 'settings', 'active', 'last_error', 'last_inbound_at'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'settings' => 'array', 'active' => 'boolean', 'last_inbound_at' => 'datetime'];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
