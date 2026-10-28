<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use BelongsToBusiness;

    protected $table = 'ai_usage';

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'purpose', 'provider', 'model', 'input_tokens', 'output_tokens', 'status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
