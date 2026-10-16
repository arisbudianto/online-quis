<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizJoinController extends Controller
{
    public function showForm(Request $request)
    {
        // Kalau datang dari kotak "Have a quiz code?" di halaman login (?code=XXXXXX),
        // kode itu otomatis diisikan ke form supaya mahasiswa tidak perlu ketik ulang.
        $prefillCode = strtoupper((string) $request->query('code', ''));

        return view('mahasiswa.quizzes.join', compact('prefillCode'));
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
        ]);

        $code = strtoupper(trim($data['code']));
        $quiz = Quiz::where('code', $code)->first();

        if (!$quiz) {
            return back()->withErrors(['code' => 'Kode kuis tidak ditemukan. Periksa kembali kode yang diberikan dosen Anda.'])
                ->withInput();
        }

        $attempts = Attempt::where('quiz_id', $quiz->id)
            ->where('user_id', $request->user()->id)
            ->get(['id', 'status']);

        return view('mahasiswa.quizzes.found', [
            'quiz' => $quiz,
            'used' => $attempts->count(),
            'ongoing' => $attempts->contains('status', 'in_progress'),
        ]);
    }
}
