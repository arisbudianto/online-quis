<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEnrollmentTest extends TestCase
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

    private function mhs(string $nim, string $kelas = 'A'): User
    {
        return User::factory()->create(['role' => 'mahasiswa', 'nim' => $nim, 'kelas' => $kelas, 'name' => 'Mhs ' . $nim]);
    }

    private function enrolledNims(?AcademicPeriod $period = null): array
    {
        return CourseEnrollment::where('course_id', $this->course->id)
            ->where('academic_period_id', ($period ?? $this->period)->id)
            ->with('student')->get()->pluck('student.nim')->sort()->values()->all();
    }

    public function test_page_shows_active_period_and_participants(): void
    {
        $s = $this->mhs('K3526001');
        CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);

        $this->actingAs($this->dosen)
            ->get(route('dosen.enrollments.index', $this->course))
            ->assertOk()
            ->assertSee('2026/2027 Ganjil')
            ->assertSee('K3526001');
    }

    public function test_add_by_nim_list_reports_duplicates_and_unknown(): void
    {
        $this->mhs('K3526001');
        $this->mhs('K3526002');
        $dosenLain = User::factory()->create(['role' => 'dosen', 'nim' => 'K3526999']);

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeNims', $this->course), [
                'academic_period_id' => $this->period->id,
                'nims' => "k3526001\nK3526002, K3526002 ; K3526404\nK3526999",
            ])
            ->assertSessionHas('status', '2 mahasiswa ditambahkan.')
            ->assertSessionHasErrors('nims');

        $this->assertSame(['K3526001', 'K3526002'], $this->enrolledNims());

        // Diulang: tidak menggandakan.
        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeNims', $this->course), [
                'academic_period_id' => $this->period->id,
                'nims' => 'K3526001',
            ])
            ->assertSessionHas('status', '0 mahasiswa ditambahkan. 1 sudah terdaftar sebelumnya.');

        $this->assertSame(2, CourseEnrollment::count());
    }

    public function test_add_by_kelas_with_angkatan(): void
    {
        $this->mhs('K3524001', 'A');
        $this->mhs('K3525001', 'A');
        $this->mhs('K3524002', 'B');

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeKelas', $this->course), [
                'academic_period_id' => $this->period->id,
                'kelas' => 'A',
                'angkatan' => '2024',
            ])
            ->assertSessionHas('status', '1 mahasiswa kelas A angkatan 2024 ditambahkan dari 1 yang cocok.');

        $this->assertSame(['K3524001'], $this->enrolledNims());

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeKelas', $this->course), [
                'academic_period_id' => $this->period->id,
                'kelas' => 'A',
            ]);

        $this->assertSame(['K3524001', 'K3525001'], $this->enrolledNims());
    }

    public function test_enrollment_is_per_period(): void
    {
        $other = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);
        $this->mhs('K3526001');

        $this->actingAs($this->dosen)->post(route('dosen.enrollments.storeNims', $this->course), [
            'academic_period_id' => $other->id, 'nims' => 'K3526001',
        ]);

        $this->assertSame([], $this->enrolledNims());
        $this->assertSame(['K3526001'], $this->enrolledNims($other));
    }

    public function test_remove_participant(): void
    {
        $s = $this->mhs('K3526001');
        $e = CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);

        $this->actingAs($this->dosen)->delete(route('dosen.enrollments.destroy', [$this->course, $e]));

        $this->assertNull($e->fresh());
        $this->assertNotNull($s->fresh(), 'Akun mahasiswa tidak ikut terhapus.');
    }

    public function test_other_dosen_cannot_view_or_change_participants(): void
    {
        $s = $this->mhs('K3526001');
        $e = CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);
        $other = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($other)->get(route('dosen.enrollments.index', $this->course))->assertForbidden();
        $this->actingAs($other)->post(route('dosen.enrollments.storeNims', $this->course), [
            'academic_period_id' => $this->period->id, 'nims' => 'K3526001',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('dosen.enrollments.destroy', [$this->course, $e]))->assertForbidden();

        $this->assertNotNull($e->fresh());
    }

    public function test_enrollment_of_another_course_cannot_be_deleted_through_this_course(): void
    {
        $s = $this->mhs('K3526001');
        $otherCourse = Course::create(['dosen_id' => User::factory()->create(['role' => 'dosen'])->id, 'name' => 'Lain']);
        $foreign = CourseEnrollment::create(['course_id' => $otherCourse->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);

        $this->actingAs($this->dosen)
            ->delete(route('dosen.enrollments.destroy', [$this->course, $foreign]))
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    public function test_period_with_participants_cannot_be_deleted(): void
    {
        $s = $this->mhs('K3526001');
        CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.periods.destroy', $this->period))
            ->assertSessionHasErrors('period');

        $this->assertNotNull($this->period->fresh());
    }

    public function test_add_all_kelas_a_b_c_skips_other_kelas_and_duplicates(): void
    {
        $this->mhs('K3526001', 'A');
        $this->mhs('K3526002', 'B');
        $this->mhs('K3526003', 'C');
        $this->mhs('K3525004', 'A');
        $this->mhs('K3526005', 'TI-3A');   // kelas di luar daftar resmi
        $this->mhs('K3526002X', 'B')->update(['role' => 'dosen']);

        $this->actingAs($this->dosen)->get(route('dosen.enrollments.index', $this->course))
            ->assertOk()->assertSee('Daftarkan semua mahasiswa kelas A, B, C')->assertSee('Mahasiswa aplikasi menurut angkatan dan kelas');

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeAllKelas', $this->course), ['academic_period_id' => $this->period->id])
            ->assertSessionHas('status', '4 mahasiswa kelas A, B, C ditambahkan dari 4 yang cocok.');

        $this->assertSame(['K3525004', 'K3526001', 'K3526002', 'K3526003'], $this->enrolledNims());

        // Diulang: tidak menggandakan.
        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeAllKelas', $this->course), ['academic_period_id' => $this->period->id])
            ->assertSessionHas('status', '0 mahasiswa kelas A, B, C ditambahkan dari 4 yang cocok. 4 sudah terdaftar sebelumnya.');
        $this->assertSame(4, CourseEnrollment::count());
    }

    public function test_add_all_kelas_with_angkatan_limits_to_one_angkatan(): void
    {
        $this->mhs('51106102610001', 'A');
        $this->mhs('21106232610002', 'C');
        $this->mhs('K3525003', 'B');

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeAllKelas', $this->course), [
                'academic_period_id' => $this->period->id, 'angkatan' => '2026',
            ])
            ->assertSessionHas('status', '2 mahasiswa kelas A, B, C angkatan 2026 ditambahkan dari 2 yang cocok.');

        $this->assertSame(['21106232610002', '51106102610001'], $this->enrolledNims());
    }

    public function test_add_all_kelas_rejects_unknown_angkatan(): void
    {
        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeAllKelas', $this->course), [
                'academic_period_id' => $this->period->id, 'angkatan' => '1999',
            ])
            ->assertSessionHasErrors('angkatan');

        $this->assertSame(0, CourseEnrollment::count());
    }

    public function test_page_shows_angkatan_counts_and_filters_participants(): void
    {
        $a = $this->mhs('K3524001', 'A');
        $b = $this->mhs('K3525001', 'B');
        foreach ([$a, $b] as $s) {
            CourseEnrollment::create(['course_id' => $this->course->id, 'academic_period_id' => $this->period->id, 'user_id' => $s->id]);
        }

        $this->actingAs($this->dosen)
            ->get(route('dosen.enrollments.index', $this->course))
            ->assertOk()
            ->assertSee('K3524001')->assertSee('K3525001');

        $this->actingAs($this->dosen)
            ->get(route('dosen.enrollments.index', [$this->course, 'angkatan' => '2025']))
            ->assertOk()
            ->assertSee('K3525001')
            ->assertDontSee('K3524001')
            ->assertSee('1 peserta terdaftar di 2026/2027 Ganjil, angkatan 2025.');
    }

    public function test_other_dosen_cannot_add_all_kelas(): void
    {
        $this->mhs('K3526001', 'A');
        $other = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($other)
            ->post(route('dosen.enrollments.storeAllKelas', $this->course), ['academic_period_id' => $this->period->id])
            ->assertForbidden();

        $this->assertSame(0, CourseEnrollment::count());
    }

    public function test_single_kelas_rejects_unknown_kelas(): void
    {
        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeKelas', $this->course), [
                'academic_period_id' => $this->period->id, 'kelas' => 'TI-3A',
            ])
            ->assertSessionHasErrors('kelas');
    }
}
