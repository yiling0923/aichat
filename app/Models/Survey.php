<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Survey extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['booking_id', 'customer_id', 'conversation_id', 'rating', 'comment', 'sent_at', 'answered_at', 'review_sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'answered_at' => 'datetime', 'review_sent_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
