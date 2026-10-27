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
        'interest',
        'details',
        'consented_at',
        'opted_out_at',
        'score',
        'tags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'details' => 'array',
            'consented_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }
}
