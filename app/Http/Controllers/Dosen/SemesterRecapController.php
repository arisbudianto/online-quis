<?php

namespace App\Http\Controllers\Dosen;

use App\Exports\SemesterRecapExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Services\SemesterGradeService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SemesterRecapController extends Controller
{
    public function index(Request $request, SemesterGradeService $service)
    {
        $user = $request->user();
        $periods = AcademicPeriod::latestFirst()->get();
        $courses = Course::where('dosen_id', $user->id)->orderBy('name')->get();

        [$period, $course, $kelas] = $this->selection($request, $periods, $courses);

        $recap = ($period && $course)
            ? $service->build($user, $course, $period, $kelas)
            : null;

        return view('dosen.recap.index', compact('periods', 'courses', 'period', 'course', 'kelas', 'recap'));
    }

    public function export(Request $request, SemesterGradeService $service)
    {
        $user = $request->user();
        $periods = AcademicPeriod::latestFirst()->get();
        $courses = Course::where('dosen_id', $user->id)->orderBy('name')->get();

        [$period, $course, $kelas] = $this->selection($request, $periods, $courses);
        abort_unless($period && $course, 422, 'Pilih periode dan mata kuliah dulu.');

        $recap = $service->build($user, $course, $period, $kelas);

        $fileName = 'rekap-' . str($course->name)->slug()
            . '-' . $period->year_start . '-' . $period->semester
            . ($kelas ? '-kelas-' . str($kelas)->slug() : '')
            . '.xlsx';

        return Excel::download(new SemesterRecapExport($course, $period, $recap), $fileName);
    }

    /**
     * Course diambil hanya dari milik dosen yang login. ID course milik
     * dosen lain menghasilkan 403, bukan diabaikan diam-diam.
     */
    private function selection(Request $request, $periods, $courses): array
    {
        $periodId = $request->query('periode');
        $period = $periodId
            ? $periods->firstWhere('id', (int) $periodId)
            : ($periods->firstWhere('is_active', true) ?? $periods->first());
        abort_if($periodId && !$period, 404);

        $courseId = $request->query('mk');
        $course = null;
        if ($courseId) {
            $course = $courses->firstWhere('id', (int) $courseId);
            abort_unless($course, 403);
        }

        $kelas = $request->query('kelas');
        $kelas = is_string($kelas) && $kelas !== '' ? $kelas : null;

        return [$period, $course, $kelas];
    }
}
