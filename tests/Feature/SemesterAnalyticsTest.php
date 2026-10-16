<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SemesterAnalyticsService;
use App\Services\SemesterGradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;
    private Course $course;
    private AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->create(['role' => 'dosen']);
        $this->course = Course::create(['dosen_id' => $this->dosen->id, 'name' => 'Ilmu Pendidikan']);
        $this->period = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
    }

    private function quiz(string $title, string $start): Quiz
    {
        return Quiz::create([
            'dosen_id' => $this->dosen->id, 'course_id' => $this->course->id,
            'academic_period_id' => $this->period->id, 'title' => $title,
            'duration_minutes' => 10, 'max_attempts' => 1, 'is_published' => true, 'start_time' => $start,
        ]);
    }

    private function student(string $nim, string $kelas): User
    {
        $u = User::factory()->create(['role' => 'mahasiswa', 'nim' => $nim, 'kelas' => $kelas, 'name' => 'Mhs ' . $nim]);
        CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $u->id]);

        return $u;
    }

    private function score(Quiz $q, User $u, ?float $score, bool $pending = false): void
    {
        Attempt::create([
            'quiz_id' => $q->id, 'user_id' => $u->id, 'question_order' => [],
            'started_at' => now(), 'deadline_at' => now(), 'submitted_at' => now(),
            'status' => 'submitted', 'score' => $score, 'needs_grading' => $pending,
        ]);
    }

    /** 4 peserta, 2 kuis. Nilai akhir: A1=90, A2=70, B1=40 (lewatkan Q2), B2 belum final. */
    private function scenario(): array
    {
        $q1 = $this->quiz('Q1', '2026-09-01 08:00:00');
        $q2 = $this->quiz('Q2', '2026-09-08 08:00:00');
        $a1 = $this->student('K3526001', 'A');
        $a2 = $this->student('K3526002', 'A');
        $b1 = $this->student('K3526003', 'B');
        $b2 = $this->student('K3526004', 'B');

        $this->score($q1, $a1, 80); $this->score($q2, $a1, 100);
        $this->score($q1, $a2, 60); $this->score($q2, $a2, 80);
        $this->score($q1, $b1, 80);                               // Q2 tidak dikerjakan -> 0
        $this->score($q1, $b2, 50); $this->score($q2, $b2, null, true);

        return [$q1, $q2];
    }

    private function build(?string $kelas = null): array
    {
        $recap = app(SemesterGradeService::class)->build($this->dosen, $this->course, $this->period, $kelas);

        return app(SemesterAnalyticsService::class)->build($recap);
    }

    public function test_describe_matches_hand_calculation(): void
    {
        $d = SemesterAnalyticsService::describe(collect([90, 70, 40, 60]));

        $this->assertSame(4, $d['n']);
        $this->assertSame(65.0, $d['mean']);
        $this->assertSame(65.0, $d['median']);
        $this->assertSame(20.82, $d['sd']);   // sqrt(1300/3)
        $this->assertSame(40.0, $d['min']);
        $this->assertSame(90.0, $d['max']);
        $this->assertSame(55.0, $d['q1']);    // QUARTILE.INC
        $this->assertSame(75.0, $d['q3']);
    }

    public function test_describe_handles_empty_and_single(): void
    {
        $this->assertNull(SemesterAnalyticsService::describe(collect())['mean']);
        $one = SemesterAnalyticsService::describe(collect([77]));
        $this->assertSame(77.0, $one['mean']);
        $this->assertNull($one['sd']);
    }

    public function test_summary_letters_per_quiz_per_kelas_and_attention(): void
    {
        [$q1, $q2] = $this->scenario();
        $s = $this->build();

        // Nilai akhir final: 90, 70, 40. B2 belum final.
        $this->assertSame(4, $s['summary']['participants']);
        $this->assertSame(3, $s['summary']['final_count']);
        $this->assertSame(1, $s['summary']['pending']);
        $this->assertSame(66.67, $s['summary']['stats']['mean']);
        $this->assertSame(70.0, $s['summary']['stats']['median']);

        $letters = collect($s['letters'])->keyBy('letter');
        $this->assertSame(1, $letters['A']['count']);
        $this->assertSame(1, $letters['B']['count']);
        $this->assertSame(1, $letters['E']['count']);
        $this->assertSame(33.3, $letters['A']['percent']);

        [$p1, $p2] = $s['perQuiz'];
        $this->assertSame('Q1', $p1['title']);
        $this->assertSame(4, $p1['attempted']);
        $this->assertSame(100.0, $p1['participation']);
        $this->assertSame(67.5, $p1['stats']['mean']);           // (80+60+80+50)/4
        $this->assertSame(3, $p2['attempted']);                  // termasuk B2 yang esainya menunggu
        $this->assertSame(75.0, $p2['participation']);
        $this->assertSame(1, $p2['pending']);
        $this->assertSame(90.0, $p2['stats']['mean']);           // (100+80)/2, hanya nilai final

        $kelas = collect($s['perKelas'])->keyBy('kelas');
        $this->assertSame(80.0, $kelas['A']['stats']['mean']);
        $this->assertSame(0, $kelas['A']['below_c']);
        $this->assertSame(100.0, $kelas['A']['participation']);
        $this->assertSame(40.0, $kelas['B']['stats']['mean']);   // hanya B1 yang final
        $this->assertSame(1, $kelas['B']['below_c']);
        $this->assertSame(75.0, $kelas['B']['participation']);   // 3 dari 4 sel

        $this->assertSame(['K3526003'], collect($s['attention'])->pluck('student.nim')->all());
        $this->assertTrue($s['attention'][0]['below_c']);
        $this->assertSame(1, $s['attention'][0]['missed']);
    }

    public function test_kelas_filter_limits_everything(): void
    {
        $this->scenario();
        $s = $this->build('A');

        $this->assertSame(2, $s['summary']['participants']);
        $this->assertSame(80.0, $s['summary']['stats']['mean']);
        $this->assertSame(['A'], collect($s['perKelas'])->pluck('kelas')->all());
    }

    public function test_page_renders_for_owner(): void
    {
        $this->scenario();

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.analytics', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertOk()
            ->assertSee('Statistik per Kuis')
            ->assertSee('66,67')
            ->assertSee('Perlu Perhatian (1)')
            ->assertSee('K3526003');
    }

    public function test_page_without_participants_shows_hint(): void
    {
        $this->quiz('Q1', '2026-09-01 08:00:00');

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.analytics', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertOk()
            ->assertSee('Analitik belum bisa disusun');
    }

    public function test_other_dosen_and_mahasiswa_are_forbidden(): void
    {
        $this->scenario();
        $params = ['periode' => $this->period->id, 'mk' => $this->course->id];

        $this->actingAs(User::factory()->create(['role' => 'dosen']))
            ->get(route('dosen.recap.analytics', $params))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526099', 'kelas' => 'A']))
            ->get(route('dosen.recap.analytics', $params))->assertForbidden();
    }

    public function test_recap_page_links_to_analytics(): void
    {
        $this->scenario();

        $this->actingAs($this->dosen)
            ->get(route('dosen.recap.index', ['periode' => $this->period->id, 'mk' => $this->course->id]))
            ->assertSee(route('dosen.recap.analytics', ['periode' => $this->period->id, 'mk' => $this->course->id]));
    }
}
