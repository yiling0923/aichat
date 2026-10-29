<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Mail\Mailable;

class DailySummaryMail extends Mailable
{
    /** @param array<string, mixed> $s */
    public function __construct(public Business $business, public array $s, public string $url) {}

    public function build(): static
    {
        return $this->subject('Yesterday at '.$this->business->name.': '.$this->s['new_chats'].' new chats, '.$this->s['bookings_made'].' bookings')
            ->text('mail.daily-summary');
    }
}
