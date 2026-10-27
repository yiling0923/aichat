<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Practitioner extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'google_calendar_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }
}
