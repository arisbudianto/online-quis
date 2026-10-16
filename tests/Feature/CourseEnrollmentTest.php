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

    public function test_add_by_kelas_with_nim_prefix(): void
    {
        $this->mhs('K3526001', 'A');
        $this->mhs('K3525001', 'A');
        $this->mhs('K3526002', 'B');

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeKelas', $this->course), [
                'academic_period_id' => $this->period->id,
                'kelas' => 'A',
                'nim_prefix' => 'k3526',
            ]);

        $this->assertSame(['K3526001'], $this->enrolledNims());

        $this->actingAs($this->dosen)
            ->post(route('dosen.enrollments.storeKelas', $this->course), [
                'academic_period_id' => $this->period->id,
                'kelas' => 'A',
            ]);

        $this->assertSame(['K3525001', 'K3526001'], $this->enrolledNims());
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
}
