<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expirationMinutes,
        public string $purpose = 'registration',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'mechanic_access'
            ? 'Your FixMoto mechanic access code'
            : 'Verify your FixMoto email address');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-otp',
            with: [
                'code' => $this->code,
                'expirationMinutes' => $this->expirationMinutes,
                'purpose' => $this->purpose,
            ],
        );
    }
}
