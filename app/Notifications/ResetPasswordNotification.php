<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        $url = $frontendUrl . '/auth/reinitialiser-mot-de-passe'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe - Griot AI')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise.");
    }
}