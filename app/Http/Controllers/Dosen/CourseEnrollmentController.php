<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\Angkatan;
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

        $angkatan = $request->query('angkatan');
        $angkatan = Angkatan::isValid($angkatan) ? $angkatan : null;

        $enrollments = $period
            ? CourseEnrollment::where('course_id', $course->id)
                ->where('academic_period_id', $period->id)
                ->whereHas('student', fn ($q) => $q->angkatan($angkatan))
                ->with('student:id,name,nim,kelas')
                ->get()
                ->sortBy(fn ($e) => [$e->student->kelas ?? '', $e->student->nim ?? ''])
                ->values()
            : collect();

        $kelasOptions = collect(User::KELAS);

        // Jumlah mahasiswa per angkatan dan kelas resmi, ditampilkan sebelum
        // dosen menekan "daftarkan semua".
        $counts = [];
        User::where('role', 'mahasiswa')
            ->whereIn('kelas', User::KELAS)
            ->get(['nim', 'kelas'])
            ->each(function (User $u) use (&$counts) {
                $a = $u->angkatan ?? Angkatan::LAINNYA;
                $counts[$a][$u->kelas] = ($counts[$a][$u->kelas] ?? 0) + 1;
            });
        $kelasCounts = collect(User::KELAS)->mapWithKeys(
            fn ($k) => [$k => collect($counts)->sum(fn ($row) => $row[$k] ?? 0)]
        );
        $angkatanCounts = collect(Angkatan::options())->mapWithKeys(
            fn ($label, $a) => [$a => $counts[$a] ?? []]
        );

        return view('dosen.enrollments.index', compact(
            'course', 'periods', 'period', 'enrollments', 'kelasOptions', 'kelasCounts', 'angkatanCounts', 'angkatan'
        ));
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

    /** Tambah semua mahasiswa satu kelas, opsional dibatasi angkatan. */
    public function storeKelas(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'kelas' => ['required', Rule::in(User::KELAS)],
            'angkatan' => ['nullable', Rule::in(array_keys(Angkatan::options()))],
        ]);

        $ids = $this->studentIds([$data['kelas']], $data['angkatan'] ?? null);
        $added = $this->enroll($course, (int) $data['academic_period_id'], $ids);

        $scope = "kelas {$data['kelas']}" . $this->angkatanText($data['angkatan'] ?? null);

        return redirect()->route('dosen.enrollments.index', [$course, 'periode' => $data['academic_period_id']])
            ->with('status', "{$added} mahasiswa {$scope} ditambahkan dari {$ids->count()} yang cocok.");
    }

    /** Tambah semua mahasiswa kelas A, B, dan C sekaligus, opsional dibatasi angkatan. */
    public function storeAllKelas(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'angkatan' => ['nullable', Rule::in(array_keys(Angkatan::options()))],
        ]);

        $ids = $this->studentIds(User::KELAS, $data['angkatan'] ?? null);
        $added = $this->enroll($course, (int) $data['academic_period_id'], $ids);

        $scope = 'kelas ' . implode(', ', User::KELAS) . $this->angkatanText($data['angkatan'] ?? null);

        return redirect()->route('dosen.enrollments.index', [$course, 'periode' => $data['academic_period_id']])
            ->with('status', "{$added} mahasiswa {$scope} ditambahkan dari {$ids->count()} yang cocok."
                . ($ids->count() > $added ? ' ' . ($ids->count() - $added) . ' sudah terdaftar sebelumnya.' : ''));
    }

    /** @param array<int, string> $kelas */
    private function studentIds(array $kelas, ?string $angkatan): Collection
    {
        return User::where('role', 'mahasiswa')
            ->whereIn('kelas', $kelas)
            ->angkatan($angkatan)
            ->pluck('id');
    }

    private function angkatanText(?string $angkatan): string
    {
        if (!Angkatan::isValid($angkatan)) {
            return '';
        }

        return $angkatan === Angkatan::LAINNYA ? ' angkatan Lainnya' : " angkatan {$angkatan}";
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
