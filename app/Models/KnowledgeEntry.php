<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class KnowledgeEntry extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['title', 'answer', 'active'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'active' => 'boolean'];
    }
}
