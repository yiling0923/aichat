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
        return $this->subject(__('Yesterday at :name: :chats new chats, :bookings bookings', ['name' => $this->business->name, 'chats' => $this->s['new_chats'], 'bookings' => $this->s['bookings_made']]))
            ->text('mail.daily-summary');
    }
}
