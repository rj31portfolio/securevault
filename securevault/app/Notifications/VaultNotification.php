<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class VaultNotification extends Notification {
    public function __construct(public string $subjectLine,public string $messageLine) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject($this->subjectLine)->greeting('Hello '.$notifiable->name)->line($this->messageLine)->action('Open SecureVault',url('/login'))->line('If you did not expect this message, contact your vault administrator.'); }
}
