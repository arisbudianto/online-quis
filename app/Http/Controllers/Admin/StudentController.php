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
        $angkatan = \App\Support\Angkatan::isValid($angkatan) ? $angkatan : null;
        $kelas    = $request->query('kelas');

        $kelasOptions = User::where('role', 'mahasiswa')->whereNotNull('kelas')
            ->distinct()->orderBy('kelas')->pluck('kelas')->all();

        $students = User::where('role', 'mahasiswa')
            ->with('teachers:id,name')
            ->angkatan($angkatan)
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->orderBy('kelas')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'angkatan', 'kelas', 'kelasOptions'));
    }
}
