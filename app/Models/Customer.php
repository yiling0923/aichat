<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'language',
        'interest',
        'details',
        'consented_at',
        'opted_out_at',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'consented_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** May we send this person messages that they did not just ask for? */
    public function canBeMessaged(): bool
    {
        return $this->consented_at !== null && $this->opted_out_at === null;
    }
}
