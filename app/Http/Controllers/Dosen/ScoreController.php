<?php

namespace App\Http\Controllers\Dosen;

use App\Exports\ScoresExport;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ScoreController extends Controller
{
    public function index(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $attempts = $quiz->attempts()
            ->with('user')
            ->orderByRaw("FIELD(status, 'in_progress', 'submitted', 'expired')")
            ->orderByDesc('score')
            ->get();

        return view('dosen.scores.index', compact('quiz', 'attempts'));
    }

    public function export(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->dosen_id === $request->user()->id, 403);

        $fileName = 'nilai-' . str($quiz->title)->slug() . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ScoresExport($quiz), $fileName);
    }

    /**
     * Lepaskan ikatan device/browser pada attempt yang masih in_progress,
     * supaya mahasiswa bisa lanjut mengerjakan dari perangkat lain. Attempt
     * TETAP terhitung sebagai 1x percobaan (tidak membebaskan slot).
     */
    public function resetDevice(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->quiz->dosen_id === $request->user()->id, 403);

        $attempt->update(['browser_session_id' => null]);

        return back()->with('status', 'Device attempt milik ' . $attempt->user->name . ' berhasil di-reset. Mahasiswa bisa lanjut dari perangkat manapun sekarang.');
    }

    /**
     * Batalkan SEPENUHNYA attempt yang masih in_progress (misal nyangkut
     * karena masalah koneksi/device mahasiswa) -- attempt + semua jawabannya
     * DIHAPUS, sehingga tidak lagi terhitung sebagai salah satu percobaan.
     * Mahasiswa bisa langsung klik "Mulai Kuis" lagi dari awal (asal masih
     * ada sisa max_attempts).
     *
     * Beda dengan resetDevice: ini bukan cuma lepas ikatan device, tapi
     * benar-benar membatalkan percobaannya. Cocok untuk kasus attempt yang
     * memang tidak akan pernah bisa dilanjutkan/submit (koneksi putus total,
     * device rusak, dsb).
     */
    public function cancelAttempt(Request $request, Attempt $attempt)
    {
        abort_unless($attempt->quiz->dosen_id === $request->user()->id, 403);
        abort_unless($attempt->status === 'in_progress', 422, 'Hanya attempt yang masih berjalan yang bisa dibatalkan.');

        $studentName = $attempt->user->name;
        $attempt->delete(); // cascade menghapus attempt_answers terkait

        return back()->with('status', "Percobaan milik {$studentName} dibatalkan. Slot percobaannya bebas lagi, dia bisa mulai kuis dari awal.");
    }
}
