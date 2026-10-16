<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SemesterGradeService;
use App\Support\Angkatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AngkatanTest extends TestCase
{
    use RefreshDatabase;

    private function mhs(?string $nim, string $kelas = 'A'): User
    {
        return User::factory()->create(['role' => 'mahasiswa', 'nim' => $nim, 'kelas' => $kelas, 'name' => 'Mhs ' . $nim]);
    }

    public function test_detect_follows_owner_rules(): void
    {
        $this->assertSame('2023', Angkatan::detect('K3523053'));
        $this->assertSame('2024', Angkatan::detect('K3524001'));
        $this->assertSame('2024', Angkatan::detect('k3524036'), 'huruf kecil tetap dikenali');
        $this->assertSame('2025', Angkatan::detect('K3525010'));
        $this->assertSame('2026', Angkatan::detect('51106102610001'));
        $this->assertSame('2026', Angkatan::detect('21106232610088'));
        $this->assertSame('2026', Angkatan::detect('21108232610050'));

        foreach (['KK3524078', 'K3542059', 'K123', '2024010001', '', null] as $nim) {
            $this->assertNull(Angkatan::detect($nim), "NIM {$nim} tidak dikenali");
        }
    }

    public function test_options_list_years_newest_first_then_lainnya(): void
    {
        $this->assertSame(
            ['2026' => '2026', '2025' => '2025', '2024' => '2024', '2023' => '2023', 'lainnya' => 'Lainnya'],
            Angkatan::options()
        );
    }

    public function test_scope_matches_detect_for_every_option(): void
    {
        $nims = ['K3523053', 'K3524001', 'k3524036', 'K3525010', '51106102610001', '21106232610088',
                 '21108232610050', 'KK3524078', 'K3542059', '2024010001'];
        foreach ($nims as $nim) {
            $this->mhs($nim);
        }
        $this->mhs(null);

        foreach (array_keys(Angkatan::options()) as $option) {
            $expected = User::where('role', 'mahasiswa')->get()
                ->filter(fn ($u) => ($u->angkatan ?? Angkatan::LAINNYA) === (string) $option)
                ->pluck('id')->sort()->values()->all();

            $actual = User::where('role', 'mahasiswa')->angkatan((string) $option)
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, "angkatan {$option}");
        }

        $this->assertSame(4, User::angkatan('lainnya')->count());
        $this->assertSame(11, User::angkatan('tidak-ada')->count(), 'nilai tak dikenal tidak menyaring');
    }

    public function test_student_lists_filter_by_angkatan(): void
    {
        $dosen = $this->makeTeacher();
        $this->linkStudents($dosen, $this->mhs('K3523053'), $this->mhs('51106102610001'), $this->mhs('KK3524078'));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($dosen)->get(route('dosen.students.index', ['angkatan' => '2023']))
            ->assertOk()->assertSee('K3523053')->assertDontSee('51106102610001')->assertDontSee('KK3524078');

        $this->actingAs($admin)->get(route('admin.students.index', ['angkatan' => '2026']))
            ->assertOk()->assertSee('51106102610001')->assertDontSee('K3523053');

        $this->actingAs($admin)->get(route('admin.students.index', ['angkatan' => 'lainnya']))
            ->assertOk()->assertSee('KK3524078')->assertDontSee('K3523053');
    }

    public function test_recap_filters_by_angkatan_and_lists_present_options(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $course = Course::create(['dosen_id' => $dosen->id, 'name' => 'Teknologi Pembelajaran']);
        $period = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);
        Quiz::create([
            'dosen_id' => $dosen->id, 'course_id' => $course->id, 'academic_period_id' => $period->id,
            'title' => 'Kuis 1', 'duration_minutes' => 10, 'max_attempts' => 1, 'is_published' => true,
        ]);

        foreach (['K3524001', '21106232610001'] as $nim) {
            CourseEnrollment::create(['course_id' => $course->id, 'academic_period_id' => $period->id, 'user_id' => $this->mhs($nim)->id]);
        }

        $recap = app(SemesterGradeService::class)->build($dosen, $course, $period, null, '2026');
        $this->assertSame(['21106232610001'], $recap['rows']->pluck('student.nim')->all());
        $this->assertSame(['2026' => '2026', '2024' => '2024'], $recap['angkatanOptions']);
        $this->assertSame(2, $recap['enrolledCount']);

        $this->actingAs($dosen)
            ->get(route('dosen.recap.index', ['periode' => $period->id, 'mk' => $course->id, 'angkatan' => '2024']))
            ->assertOk()->assertSee('K3524001')->assertDontSee('21106232610001')
            ->assertSee('<option value="2024" selected>2024</option>', false);

        $this->actingAs($dosen)
            ->get(route('dosen.recap.export', ['periode' => $period->id, 'mk' => $course->id, 'angkatan' => '2024']))
            ->assertOk()
            ->assertDownload('rekap-teknologi-pembelajaran-2026-ganjil-angkatan-2024.xlsx');
    }
}
