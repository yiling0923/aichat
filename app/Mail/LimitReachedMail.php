<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Mail\Mailable;

class LimitReachedMail extends Mailable
{
    public function __construct(public Business $business, public string $kind) {}

    public function build(): static
    {
        return $this->subject('Plan limit reached: '.$this->business->name)
            ->text('mail.limit-reached');
    }
}
