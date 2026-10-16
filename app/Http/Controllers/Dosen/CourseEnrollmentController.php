<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CourseEnrollmentController extends Controller
{
    public function index(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $periods = AcademicPeriod::latestFirst()->get();
        $period = $this->period($request, $periods);

        $enrollments = $period
            ? CourseEnrollment::where('course_id', $course->id)
                ->where('academic_period_id', $period->id)
                ->with('student:id,name,nim,kelas')
                ->get()
                ->sortBy(fn ($e) => [$e->student->kelas ?? '', $e->student->nim ?? ''])
                ->values()
            : collect();

        $kelasOptions = collect(User::KELAS);

        // Jumlah mahasiswa per kelas resmi, ditampilkan sebelum dosen
        // menekan "daftarkan semua".
        $kelasCounts = User::where('role', 'mahasiswa')
            ->whereIn('kelas', User::KELAS)
            ->selectRaw('kelas, count(*) as total')
            ->groupBy('kelas')
            ->pluck('total', 'kelas');

        return view('dosen.enrollments.index', compact('course', 'periods', 'period', 'enrollments', 'kelasOptions', 'kelasCounts'));
    }

    /** Tambah peserta dari daftar NIM (dipisah baris, spasi, koma, atau titik koma). */
    public function storeNims(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'nims' => ['required', 'string', 'max:20000'],
        ]);

        $nims = collect(preg_split('/[\s,;]+/', strtoupper($data['nims'])))
            ->map(fn ($n) => trim($n))->filter()->unique()->values();

        $students = User::where('role', 'mahasiswa')
            ->whereIn('nim', $nims)
            ->get(['id', 'nim']);

        $notFound = $nims->diff($students->pluck('nim')->map(fn ($n) => strtoupper($n)))->values();

        $added = $this->enroll($course, (int) $data['academic_period_id'], $students->pluck('id'));

        $status = "{$added} mahasiswa ditambahkan.";
        if ($students->count() > $added) {
            $status .= ' ' . ($students->count() - $added) . ' sudah terdaftar sebelumnya.';
        }

        $redirect = redirect()->route('dosen.enrollments.index', [$course, 'periode' => $data['academic_period_id']])
            ->with('status', $status);

        if ($notFound->isNotEmpty()) {
            $redirect->withErrors(['nims' => 'NIM tidak ditemukan sebagai akun mahasiswa: ' . $notFound->implode(', ')]);
        }

        return $redirect;
    }

    /** Tambah semua mahasiswa satu kelas, opsional dibatasi awalan NIM (angkatan). */
    public function storeKelas(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'kelas' => ['required', Rule::in(User::KELAS)],
            'nim_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        $ids = $this->studentIds([$data['kelas']], $data['nim_prefix'] ?? null);
        $added = $this->enroll($course, (int) $data['academic_period_id'], $ids);

        return redirect()->route('dosen.enrollments.index', [$course, 'periode' => $data['academic_period_id']])
            ->with('status', "{$added} mahasiswa kelas {$data['kelas']} ditambahkan dari {$ids->count()} yang cocok.");
    }

    /** Tambah semua mahasiswa kelas A, B, dan C sekaligus, opsional dibatasi awalan NIM. */
    public function storeAllKelas(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'nim_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        $ids = $this->studentIds(User::KELAS, $data['nim_prefix'] ?? null);
        $added = $this->enroll($course, (int) $data['academic_period_id'], $ids);

        $scope = 'kelas ' . implode(', ', User::KELAS)
            . (!empty($data['nim_prefix']) ? ' dengan awalan NIM ' . strtoupper($data['nim_prefix']) : '');

        return redirect()->route('dosen.enrollments.index', [$course, 'periode' => $data['academic_period_id']])
            ->with('status', "{$added} mahasiswa {$scope} ditambahkan dari {$ids->count()} yang cocok."
                . ($ids->count() > $added ? ' ' . ($ids->count() - $added) . ' sudah terdaftar sebelumnya.' : ''));
    }

    /** @param array<int, string> $kelas */
    private function studentIds(array $kelas, ?string $nimPrefix): Collection
    {
        return User::where('role', 'mahasiswa')
            ->whereIn('kelas', $kelas)
            ->when(!empty($nimPrefix), fn ($q) => $q->where('nim', 'like', strtoupper($nimPrefix) . '%'))
            ->pluck('id');
    }

    public function destroy(Request $request, Course $course, CourseEnrollment $enrollment)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($enrollment->course_id === $course->id, 404);

        $periodId = $enrollment->academic_period_id;
        $name = $enrollment->student?->name;
        $enrollment->delete();

        return redirect()->route('dosen.enrollments.index', [$course, 'periode' => $periodId])
            ->with('status', "{$name} dihapus dari daftar peserta.");
    }

    /** @return int jumlah baris baru */
    private function enroll(Course $course, int $periodId, Collection $userIds): int
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $userIds->map(fn ($id) => [
            'course_id' => $course->id,
            'academic_period_id' => $periodId,
            'user_id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        $added = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $added += CourseEnrollment::insertOrIgnore($chunk);
        }

        return $added;
    }

    private function period(Request $request, Collection $periods): ?AcademicPeriod
    {
        $id = $request->query('periode');
        if ($id) {
            $period = $periods->firstWhere('id', (int) $id);
            abort_unless($period, 404);

            return $period;
        }

        return $periods->firstWhere('is_active', true) ?? $periods->first();
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($course->dosen_id === $request->user()->id, 403);
    }
}
