<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToBusiness;

    public const TYPES = ['customer', 'chat'];

    protected $fillable = ['name', 'color', 'type'];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class);
    }
}
