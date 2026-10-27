<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class BotFlow extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['draft', 'published', 'published_at'];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draft != $this->published;
    }
}
