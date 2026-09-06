<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $kelas = $request->query('kelas');

        $students = User::where('role', 'mahasiswa')
            ->when($kelas, fn ($q) => $q->where('kelas', $kelas))
            ->orderBy('kelas')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'kelas'));
    }
}
