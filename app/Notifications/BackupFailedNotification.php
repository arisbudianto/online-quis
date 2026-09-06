<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TIDAK implements ShouldQueue -- sengaja SYNCHRONOUS. Kalau backup gagal
 * (misal karena queue/cron sedang bermasalah), kita tidak mau notifikasi
 * kegagalan ini malah ikut nyangkut di antrian yang sama. Backup jarang
 * terjadi (1x sehari) jadi kirim langsung tidak masalah dari sisi performa.
 */
class BackupFailedNotification extends Notification
{
    public function __construct(
        private string $errorMessage,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'Backup database GAGAL pada ' . now()->format('d-m-Y H:i') . '.',
            'error' => $this->errorMessage,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠ Backup Database Gagal - Kuis Online')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Proses backup database otomatis GAGAL pada ' . now()->format('d-m-Y H:i') . '.')
            ->line('Pesan error: ' . $this->errorMessage)
            ->line('Segera cek server -- kemungkinan disk penuh atau kredensial database berubah.')
            ->line('Backup terakhir yang berhasil (kalau ada) masih tersimpan aman, tidak ikut terhapus.');
    }
}
