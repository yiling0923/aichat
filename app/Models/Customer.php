<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'language',
        'score',
        'tags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
