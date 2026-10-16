<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Dosen/guru mengatur daftar kelas yang bisa dipilih siswanya. */
class TeacherClassController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $this->teacher($request);

        $classes = $teacher->teacherClasses()->orderBy('name')->get();
        $counts = User::visibleTo($teacher)->whereIn('kelas', $classes->pluck('name'))
            ->selectRaw('kelas, count(*) as total')->groupBy('kelas')->pluck('total', 'kelas');

        return view('dosen.classes.index', compact('classes', 'counts'));
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher($request);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:30', 'regex:/^[\pL\pN .\-\/]+$/u',
                Rule::unique('teacher_classes', 'name')->where('teacher_id', $teacher->id),
            ],
        ], [
            'name.unique' => 'Kelas ini sudah ada.',
            'name.regex' => 'Nama kelas hanya boleh huruf, angka, spasi, titik, garis miring, dan tanda minus.',
        ]);

        $teacher->teacherClasses()->create(['name' => trim($data['name'])]);

        return back()->with('status', "Kelas {$data['name']} ditambahkan.");
    }

    public function destroy(Request $request, TeacherClass $teacherClass)
    {
        $teacher = $this->teacher($request);
        abort_unless($teacherClass->teacher_id === $teacher->id, 404);

        $teacherClass->delete();

        return back()->with('status', "Kelas {$teacherClass->name} dihapus dari daftar. Data kelas siswa yang sudah ada tidak diubah.");
    }

    private function teacher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->role === 'dosen', 403, 'Daftar kelas hanya untuk akun dosen/guru.');

        return $user;
    }
}
