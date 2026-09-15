<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $attempts = Attempt::with(['quiz.course'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'submitted')
            ->orderByDesc('submitted_at')
            ->paginate(20);

        return view('mahasiswa.history.index', compact('attempts'));
    }
}
