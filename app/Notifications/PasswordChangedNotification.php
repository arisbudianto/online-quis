<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Akun Kuis Online Anda Telah Diubah')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Password akun Anda baru saja berhasil diubah pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB.')
            ->line('Jika Anda yang melakukan perubahan ini, tidak ada tindakan lebih lanjut yang diperlukan.')
            ->line('**Jika Anda TIDAK merasa mengubah password**, segera hubungi dosen/admin sistem untuk mengamankan akun Anda.')
            ->action('Login ke Akun', route('login'));
    }
}
