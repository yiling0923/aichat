<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One bell notification for one staff member. Always read through the signed-in user, never by business. */
class StaffNotification extends Model
{
    protected $fillable = ['business_id', 'user_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
