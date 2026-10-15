<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetStudentPassword extends Notification
{
    use Queueable;

    public function __construct(public string $resetUrl, public int $expiresMinutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your CFO student account password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A password reset was requested for your verified student account.')
            ->action('Reset Password', $this->resetUrl)
            ->line("This single-use link expires in {$this->expiresMinutes} minutes.")
            ->line('If you did not request this reset, no action is required.');
    }
}
