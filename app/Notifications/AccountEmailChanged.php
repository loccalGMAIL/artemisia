<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountEmailChanged extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly string $oldEmail,
        public readonly string $newEmail,
        public readonly bool $forNewAddress,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}
