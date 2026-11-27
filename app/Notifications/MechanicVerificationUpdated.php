<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class MechanicVerificationUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public string $status,
        public string $message,
        public ?string $documentType = null,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'document_type' => $this->documentType,
            'reason' => $this->reason,
        ];
    }
}
