<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'duration_min', 'price', 'active', 'sort'];

    protected function casts(): array
    {
        return ['name' => 'array', 'active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function practitioners(): BelongsToMany
    {
        return $this->belongsToMany(Practitioner::class);
    }

    public function label(?string $language): string
    {
        $name = (array) $this->name;

        return (string) ($name[$language] ?? $name['en'] ?? reset($name) ?: '');
    }
}
