<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Mail\Mailable;

class PlanReminderMail extends Mailable
{
    public function __construct(public Business $business, public string $state) {}

    public function build(): static
    {
        return $this->subject(__('Your plan needs attention: :name', ['name' => $this->business->name]))->text('mail.plan-reminder');
    }
}
