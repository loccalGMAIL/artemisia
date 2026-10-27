<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells both the old and the new address that the email of an account changed (spec 006, RF-17).
 */
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
        $line = $this->forNewAddress
            ? __('accounts.email_changed.new_address', ['app' => __('brand.name'), 'email' => $this->oldEmail])
            : __('accounts.email_changed.old_address', ['app' => __('brand.name'), 'email' => $this->newEmail]);

        return (new MailMessage)
            ->subject(__('accounts.email_changed.subject', ['app' => __('brand.name')]))
            ->greeting(__('accounts.email_changed.greeting', ['name' => $this->name]))
            ->line($line)
            ->line(__('accounts.email_changed.unexpected'));
    }
}
