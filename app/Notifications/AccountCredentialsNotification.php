<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $plainPassword,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Kuis Online Anda Sudah Aktif')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Akun Anda di sistem Kuis Online sudah berhasil dibuat. Berikut detail login Anda:')
            ->line('**Email (username):** ' . $notifiable->email)
            ->line('**NIM:** ' . ($notifiable->nim ?? '-'))
            ->line('**Password:** ' . $this->plainPassword)
            ->line('Demi keamanan, segera login dan pertimbangkan untuk mengganti password Anda melalui halaman profil.')
            ->action('Login Sekarang', route('login'))
            ->line('Jangan bagikan email ini atau password Anda kepada siapa pun, termasuk pihak yang mengaku sebagai admin/dosen.');
    }
}
