<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'message', 'template_name', 'audience', 'status', 'sent_at', 'sent_count', 'skipped_count', 'failed_count', 'created_by'];

    protected function casts(): array
    {
        return ['message' => 'array', 'audience' => 'array', 'sent_at' => 'datetime'];
    }
}
