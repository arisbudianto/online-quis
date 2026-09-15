<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::where('dosen_id', $request->user()->id)
            ->withCount(['quizzes', 'bankQuestions'])
            ->orderBy('name')
            ->get();

        return view('dosen.courses.index', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Course::create([
            'dosen_id' => $request->user()->id,
            'name' => $data['name'],
        ]);

        return back()->with('status', 'Mata kuliah ditambahkan.');
    }

    public function destroy(Request $request, Course $course)
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);

        $course->delete();

        return back()->with('status', 'Mata kuliah dihapus. Kuis yang terkait tidak ikut terhapus, hanya tidak lagi terhubung ke mata kuliah ini.');
    }
}
