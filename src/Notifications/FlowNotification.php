<?php

namespace MightyMango\Flowkit\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mail sent by `core.notify`. The message is plain text: each line goes
 * in as a MailMessage line, which the mail template escapes. It is never
 * compiled as Blade.
 */
class FlowNotification extends Notification
{
    public function __construct(
        public readonly string $subject,
        public readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject);

        foreach (preg_split('/\R/', $this->message) as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
