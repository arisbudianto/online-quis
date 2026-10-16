<?php

namespace Tests\Feature;

use App\Exports\SemesterRecapExport;
use App\Models\AcademicPeriod;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SemesterGradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SemesterRecapTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;
    private Course $course;
    private AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->create(['role' => 'dosen']);
        $this->course = Course::create(['dosen_id' => $this->dosen->id, 'name' => 'Basis Data']);
        $this->period = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
    }

    private function quiz(array $attrs = []): Quiz
    {
        return Quiz::create(array_merge([
            'dosen_id' => $this->dosen->id,
            'course_id' => $this->course->id,
            'academic_period_id' => $this->period->id,
            'title' => 'Kuis ' . uniqid(),
            'duration_minutes' => 10,
            'max_attempts' => 3,
            'is_published' => true,
        ], $attrs));
    }

    /** Mahasiswa baru, otomatis didaftarkan sebagai peserta resmi kecuali $enroll = false. */
    private function student(string $nim, string $kelas = 'A', bool $enroll = true): User
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'nim' => $nim, 'kelas' => $kelas, 'name' => 'Mhs ' . $nim]);

        if ($enroll) {
            CourseEnrollment::create([
                'course_id' => $this->course->id,
                'academic_period_id' => $this->period->id,
                'user_id' => $user->id,
            ]);
        }

        return $user;
    }

    public function test_enrolled_student_without_any_attempt_gets_zero_and_e(): void
    {
        $q = $this->quiz();
        $this->attempt($q, $this->student('K3526070'), 90);
        $this->student('K3526071');   // terdaftar, tidak mengerjakan apa pun

        $rows = $this->build()['rows']->keyBy(fn ($r) => $r['student']->nim);

        $this->assertSame(0.0, $rows['K3526071']['final']);
        $this->assertSame('E', $rows['K3526071']['letter']);
        $this->assertSame('A', $rows['K3526070']['letter']);
    }

    public function test_unenrolled_student_is_reported_but_not_in_rows(): void
    {
        $q = $this->quiz();
        $this->attempt($q, $this->student('K3526080'), 70);
        $outsider = $this->student('K3526081', 'B', false);
        $this->attempt($q, $outsider, 95);

        $recap = $this->build();

        $this->assertSame(['K3526080'], $recap['rows']->pluck('student.nim')->all());
        $this->assertSame(1, $recap['enrolledCount']);
        $this->assertSame(['K3526081'], $recap['unenrolled']->pluck('nim')->all());
    }

    public function test_enrollment_of_other_period_or_course_does_not_count(): void
    {
        $q = $this->quiz();
        $other = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $s = $this->student('K3526090', 'A', false);
        CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $other->id, 'user_id' => $s->id]);
        $this->attempt($q, $s, 80);

        $recap = $this->build();

        $this->assertSame(0, $recap['enrolledCount']);
        $this->assertSame(['K3526090'], $recap['unenrolled']->pluck('nim')->all());
    }

    public function test_page_warns_when_no_participants(): void
    {
        $q = $this->quiz();
        $this->attempt($q, $this->student('K3526095', 'A', false), 80);

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.index', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertOk()
            ->assertSee('Belum ada peserta resmi')
            ->assertSee('Sudah ada 1 mahasiswa');
    }

    private function attempt(Quiz $quiz, User $student, ?float $score, string $status = 'submitted', bool $needsGrading = false): Attempt
    {
        return Attempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'question_order' => [],
            'started_at' => now()->subHour(),
            'deadline_at' => now()->subMinutes(30),
            'submitted_at' => $status === 'submitted' ? now()->subMinutes(40) : null,
            'status' => $status,
            'score' => $score,
            'needs_grading' => $needsGrading,
        ]);
    }

    private function build(?string $kelas = null): array
    {
        return app(SemesterGradeService::class)->build($this->dosen, $this->course, $this->period, $kelas);
    }

    public function test_final_is_average_with_first_attempt_and_missing_as_zero(): void
    {
        $q1 = $this->quiz(['title' => 'Q1']);
        $q2 = $this->quiz(['title' => 'Q2']);
        $q3 = $this->quiz(['title' => 'Q3']);
        $s = $this->student('K3526001');

        $this->attempt($q1, $s, 90);
        $this->attempt($q1, $s, 30);   // data lama: pengerjaan kedua diabaikan
        $this->attempt($q2, $s, 75);
        // Q3 tidak dikerjakan => 0

        $row = $this->build()['rows']->first();

        $this->assertSame(90.0, $row['cells'][$q1->id]['score']);
        $this->assertSame(2, $row['cells'][$q1->id]['attempts']);
        $this->assertSame(SemesterGradeService::CELL_NONE, $row['cells'][$q3->id]['status']);
        $this->assertTrue($row['complete']);
        $this->assertSame(55.0, $row['final'], '(90 + 75 + 0) / 3 = 55');
        $this->assertSame('D', $row['letter']);
        $this->assertSame(2.0, $row['weight']);
    }

    public function test_pending_essay_makes_final_not_final(): void
    {
        $q1 = $this->quiz();
        $q2 = $this->quiz();
        $s = $this->student('K3526002');

        $this->attempt($q1, $s, 80);
        $this->attempt($q2, $s, null, 'submitted', true);

        $row = $this->build()['rows']->first();

        $this->assertSame(SemesterGradeService::CELL_PENDING, $row['cells'][$q2->id]['status']);
        $this->assertFalse($row['complete']);
        $this->assertNull($row['final']);
        $this->assertNull($row['letter'], 'Belum final tidak boleh diberi huruf.');
    }

    public function test_only_first_attempt_counts_for_legacy_duplicates(): void
    {
        $q = $this->quiz();
        $s = $this->student('K3526003');

        $this->attempt($q, $s, 70);
        $this->attempt($q, $s, 100);
        $this->attempt($q, $s, null, 'submitted', true);

        $row = $this->build()['rows']->first();

        $this->assertSame(SemesterGradeService::CELL_FINAL, $row['cells'][$q->id]['status']);
        $this->assertSame(70.0, $row['final']);
    }

    public function test_in_progress_attempt_is_ignored(): void
    {
        $q1 = $this->quiz();
        $q2 = $this->quiz();
        $s = $this->student('K3526004');

        $this->attempt($q1, $s, 100);
        $this->attempt($q2, $s, null, 'in_progress');

        $row = $this->build()['rows']->first();

        $this->assertSame(SemesterGradeService::CELL_NONE, $row['cells'][$q2->id]['status']);
        $this->assertSame(50.0, $row['final']);
    }

    public function test_only_quizzes_of_this_course_period_and_dosen_are_counted(): void
    {
        $counted = $this->quiz(['title' => 'DIHITUNG']);
        $otherPeriod = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $otherCourse = Course::create(['dosen_id' => $this->dosen->id, 'name' => 'Jarkom']);
        $otherDosen = User::factory()->create(['role' => 'dosen']);

        $qPeriod = $this->quiz(['academic_period_id' => $otherPeriod->id]);
        $qCourse = $this->quiz(['course_id' => $otherCourse->id]);
        $qNoPeriod = $this->quiz(['academic_period_id' => null]);
        $qOtherDosen = $this->quiz(['dosen_id' => $otherDosen->id]);

        $s = $this->student('K3526005');
        foreach ([$counted, $qPeriod, $qCourse, $qNoPeriod, $qOtherDosen] as $q) {
            $this->attempt($q, $s, 40);
        }

        $recap = $this->build();

        $this->assertSame([$counted->id], $recap['quizzes']->pluck('id')->all());
        $this->assertSame(40.0, $recap['rows']->first()['final']);
    }

    public function test_rows_sorted_by_kelas_then_nim_and_filter_by_kelas(): void
    {
        $q = $this->quiz();
        $b2 = $this->student('K3526020', 'B');
        $a2 = $this->student('K3526011', 'A');
        $a1 = $this->student('K3526010', 'A');
        foreach ([$b2, $a2, $a1] as $s) {
            $this->attempt($q, $s, 50);
        }

        $recap = $this->build();
        $this->assertSame(['K3526010', 'K3526011', 'K3526020'], $recap['rows']->pluck('student.nim')->all());
        $this->assertSame(['A', 'B'], $recap['kelasOptions']);

        $onlyB = $this->build('B');
        $this->assertSame(['K3526020'], $onlyB['rows']->pluck('student.nim')->all());
        $this->assertSame(['A', 'B'], $onlyB['kelasOptions'], 'Opsi kelas tetap lengkap saat difilter.');
    }

    public function test_page_shows_recap_for_owner(): void
    {
        $q = $this->quiz(['title' => 'UTS Basis Data']);
        $this->attempt($q, $this->student('K3526030'), 87.5);

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.index', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertOk()
            ->assertSee('UTS Basis Data')
            ->assertSee('K3526030')
            ->assertSee('87,5');
    }

    public function test_page_without_course_asks_for_selection(): void
    {
        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.index'))
            ->assertOk()
            ->assertSee('Pilih periode dan mata kuliah');
    }

    public function test_other_dosen_cannot_open_or_export_recap_of_foreign_course(): void
    {
        $q = $this->quiz();
        $this->attempt($q, $this->student('K3526040'), 90);
        $other = User::factory()->create(['role' => 'dosen']);

        $params = ['periode' => $this->period->id, 'mk' => $this->course->id];

        $this->actingAs($other)->get(route('dosen.recap.index', $params))->assertForbidden();
        $this->actingAs($other)->get(route('dosen.recap.export', $params))->assertForbidden();
    }

    public function test_mahasiswa_cannot_open_recap(): void
    {
        $this->actingAs($this->student('K3526050'))
            ->get(route('dosen.recap.index'))
            ->assertForbidden();
    }

    public function test_export_matches_page_values(): void
    {
        Excel::fake();

        $q1 = $this->quiz(['title' => 'Q1']);
        $q2 = $this->quiz(['title' => 'Q2']);
        $s = $this->student('K3526060');
        $this->attempt($q1, $s, 80);

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.export', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertOk();

        Excel::assertDownloaded('rekap-basis-data-2026-ganjil.xlsx', function (SemesterRecapExport $export) {
            $this->assertSame(['NIM', 'Nama', 'Kelas', 'Q1', 'Q2', 'Nilai Akhir', 'Huruf', 'Bobot', 'Keterangan'], $export->headings());
            $this->assertSame(
                [['K3526060', 'Mhs K3526060', 'A', 80.0, 'Tidak mengerjakan', 40.0, 'E', 0.0, 'Final']],
                $export->array()
            );
            return true;
        });
    }
}
