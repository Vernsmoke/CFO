<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyStudentEmail extends Notification
{
    use Queueable;

    public function __construct(
        public string $verificationUrl,
        public int $expiresMinutes,
        public bool $emailChange = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->emailChange ? 'Verify your new CFO email address' : 'Verify your CFO student account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->emailChange
                ? 'A new email address was entered in your CFO profile. Verify it before it becomes your sign-in email.'
                : 'Your student registration was received. Verify your email address to activate your account.')
            ->action($this->emailChange ? 'Verify New Email Address' : 'Verify Student Account', $this->verificationUrl)
            ->line("This single-use link expires in {$this->expiresMinutes} minutes.")
            ->line($this->emailChange
                ? 'Your current verified email remains your sign-in email until this change is verified.'
                : 'If you did not submit this registration, you can ignore this email.');
    }
}
