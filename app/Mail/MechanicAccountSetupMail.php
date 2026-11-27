<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MechanicAccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Set up your FixMoto mechanic account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mechanic-account-setup', with: [
            'user' => $this->user,
            'setupUrl' => route('mechanic.password.setup', ['token' => $this->token, 'email' => $this->user->email]),
            'expirationMinutes' => config('auth.passwords.users.expire', 60),
        ]);
    }
}
