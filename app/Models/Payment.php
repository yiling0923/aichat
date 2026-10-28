<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A payment taken for a business. Not tenant-scoped: the platform owner sums these across businesses. */
class Payment extends Model
{
    protected $fillable = ['business_id', 'plan', 'amount_cents', 'currency', 'provider', 'reference', 'status'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
