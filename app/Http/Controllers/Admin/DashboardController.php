<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_dosen' => User::where('role', 'dosen')->count(),
            'total_mahasiswa' => User::where('role', 'mahasiswa')->count(),
            'total_kuis' => Quiz::count(),
            'total_attempt' => Attempt::where('status', 'submitted')->count(),
        ];

        return view('admin.dashboard.index', compact('stats'));
    }
}
