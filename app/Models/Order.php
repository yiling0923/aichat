<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['customer_id', 'reference', 'items', 'amount', 'status', 'notes', 'ordered_at'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
