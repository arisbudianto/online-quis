<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Klik satu notifikasi: tandai dibaca, lalu arahkan ke halaman "Masukkan
     * Kode Kuis" (mahasiswa tinggal ketik kode yang tertera di notifikasi).
     */
    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->route('mahasiswa.quizzes.join');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
