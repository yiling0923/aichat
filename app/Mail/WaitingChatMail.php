<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Mail\Mailable;

class WaitingChatMail extends Mailable
{
    public function __construct(public Business $business, public string $name, public int $minutes, public bool $urgent, public string $url, public ?string $reason = null) {}

    public function build(): static
    {
        return $this->subject(($this->urgent ? __('URGENT:').' ' : '').__(':who has waited :n minutes (:name)', ['who' => $this->name, 'n' => $this->minutes, 'name' => $this->business->name]))
            ->text('mail.waiting-chat');
    }
}
