<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MechanicApplicationSubmitted extends Notification
{
    use Queueable;

    public function __construct(public int $userId, public string $shopName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => 'mechanic_application',
            'message' => "New mechanic application: {$this->shopName}",
            'url' => route('admin.verification.show', $this->userId),
            'user_id' => $this->userId,
        ];
    }
}
