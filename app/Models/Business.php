<?php

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'default_language',
        'languages',
        'settings',
        'status',
        'plan',
        'subscription_status',
        'trial_ends_at',
        'current_period_ends_at',
        'paused_reason',
        'setup_done_at',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'setup_done_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
