<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi database SAJA (untuk lonceng navbar), sengaja TIDAK
 * implements ShouldQueue -- supaya langsung tersimpan & muncul di lonceng
 * tanpa perlu menunggu cron/queue worker jalan. Ini murni INSERT ke tabel
 * `notifications`, sangat ringan walau dikirim ke ratusan mahasiswa sekaligus.
 */
class QuizPublishedNotification extends Notification
{
    public function __construct(
        private Quiz $quiz,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'quiz_id' => $this->quiz->id,
            'quiz_title' => $this->quiz->title,
            'quiz_code' => $this->quiz->code,
            'course_name' => $this->quiz->course->name ?? null,
            'message' => 'Kuis baru "' . $this->quiz->title . '" sudah dibuka.',
        ];
    }
}
