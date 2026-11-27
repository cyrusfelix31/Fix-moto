<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MechanicAvailabilityNotice extends Notification
{
    use Queueable;

    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'status' => 'mechanics_unavailable',
            'message' => $this->message,
            'url' => route('mechanic.team'),
        ];
    }
}
