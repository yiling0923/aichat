<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class BotEvent extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'type', 'node', 'option_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
