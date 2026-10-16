<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $angkatan = $request->query('angkatan');
        $kelas    = $request->query('kelas');

        $students = User::where('role', 'mahasiswa')
            ->when($angkatan, function ($q) use ($angkatan) {
                if ($angkatan === '2024') {
                    // Angkatan 2024: NIM diawali K3524
                    $q->where('nim', 'like', 'K3524%');
                } elseif ($angkatan === '2025') {
                    // Angkatan 2025: NIM diawali K3525
                    $q->where('nim', 'like', 'K3525%');
                } elseif ($angkatan === '2026') {
                    // Angkatan 2026: NIM panjang numerik (contoh: 21106232610061)
                    // = bukan pola K3524/K3525, dan NIM berisi digit panjang
                    $q->where(function ($q2) {
                        $q2->whereRaw("nim REGEXP '^[0-9]{10,}$'")
                           ->orWhere(function ($q3) {
                               $q3->where('nim', 'not like', 'K3524%')
                                  ->where('nim', 'not like', 'K3525%')
                                  ->whereRaw("nim REGEXP '[0-9]{10,}'");
                           });
                    });
                }
            })
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->orderBy('kelas')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'angkatan', 'kelas'));
    }
}
