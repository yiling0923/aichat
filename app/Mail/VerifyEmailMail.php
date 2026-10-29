<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    public string $link;

    public function __construct(public User $user)
    {
        $this->link = URL::temporarySignedRoute('verification.verify', now()->addDays(2), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }

    public function build(): static
    {
        return $this->subject(__('Confirm your email'))->text('mail.verify');
    }
}
