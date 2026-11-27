<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MechanicDocumentResubmitted extends Notification
{
    use Queueable;

    public function __construct(public int $userId, public string $shopName, public array $documents) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => 'mechanic_document_resubmitted',
            'message' => "{$this->shopName} resubmitted: " . implode(', ', $this->documents),
            'url' => route('admin.verification.show', $this->userId),
            'user_id' => $this->userId,
        ];
    }
}
