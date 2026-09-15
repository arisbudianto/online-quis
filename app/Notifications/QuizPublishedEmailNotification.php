<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi EMAIL saja, implements ShouldQueue -- dikirim lewat antrian
 * (tabel `jobs`), diproses bertahap oleh cron job, TIDAK memblokir request
 * "Simpan Pengaturan" dosen walau audiencenya ratusan mahasiswa.
 */
class QuizPublishedEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Quiz $quiz,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kuis Baru Dibuka: ' . $this->quiz->title)
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Dosen Anda baru saja membuka kuis baru:')
            ->line('**' . $this->quiz->title . '**' . ($this->quiz->course ? ' (' . $this->quiz->course->name . ')' : ''))
            ->line('Kode Kuis: **' . $this->quiz->code . '**')
            ->action('Kerjakan Sekarang', route('mahasiswa.quizzes.join'))
            ->line('Masukkan kode di atas pada halaman "Masukkan Kode Kuis" untuk mulai mengerjakan.');
    }
}
