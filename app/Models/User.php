<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Access;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'business_id',
        'role',
        'permissions',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Owner or manager: may edit the library, tags and other shared settings. */
    public function canManage(): bool
    {
        return in_array($this->role, ['owner', 'manager'], true);
    }

    /** May this person use an area (Access::AREAS)? Owners always may; others get the owner's choice, or their role's usual access. */
    public function allows(string $area): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        $list = is_array($this->permissions) ? $this->permissions : Access::forRole((string) $this->role);

        return in_array($area, $list, true);
    }

    /** @return list<string> */
    public function areas(): array
    {
        return $this->role === 'owner' ? Access::keys() : (is_array($this->permissions) ? Access::clean($this->permissions) : Access::forRole((string) $this->role));
    }

    public function isPlatformOwner(): bool
    {
        return $this->role === 'platform_owner';
    }
}
