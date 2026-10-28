<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['key', 'enabled', 'delay_hours', 'message', 'template_name', 'settings'];

    protected function casts(): array
    {
        return ['message' => 'array', 'settings' => 'array', 'enabled' => 'boolean'];
    }
}
