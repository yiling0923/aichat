<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['title', 'body', 'sort'];

    protected function casts(): array
    {
        return ['body' => 'array'];
    }
}
