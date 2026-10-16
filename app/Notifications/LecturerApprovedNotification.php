<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LecturerApprovedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun dosen/guru Kuis Online sudah aktif')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Pendaftaran Anda sebagai dosen/guru di Kuis Online sudah disetujui admin.')
            ->line('Langkah pertama: atur daftar kelas Anda supaya siswa bisa memilih kelas saat mendaftar.')
            ->action('Masuk ke Kuis Online', route('login'));
    }
}
