<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Belongs to a broadcast, which belongs to a business (no business_id of its own). */
class BroadcastRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['broadcast_id', 'customer_id', 'status', 'reason'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
