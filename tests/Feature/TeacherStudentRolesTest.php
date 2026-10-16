<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\TeacherClass;
use App\Models\User;
use App\Notifications\LecturerApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeacherStudentRolesTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Pendaftar',
            'email' => 'baru@contoh.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $override);
    }

    public function test_register_as_teacher_waits_for_admin_approval(): void
    {
        Notification::fake();

        $this->post('/register', $this->registerPayload([
            'role' => 'dosen', 'institution' => 'SMA Harapan Bangsa',
        ]))->assertRedirect('/dashboard');

        $guru = User::where('email', 'baru@contoh.test')->firstOrFail();
        $this->assertSame('dosen', $guru->role);
        $this->assertTrue($guru->isPendingApproval());
        $this->assertSame('SMA Harapan Bangsa', $guru->institution);
        $this->assertNull($guru->nim);

        // Pendaftaran lewat formulir tetap wajib verifikasi email dulu.
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $guru->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($guru->fresh());

        $this->get('/dashboard')->assertRedirect(route('dosen.pending'));
        $this->get(route('dosen.pending'))->assertOk()->assertSee('sedang diperiksa');
        $this->get(route('dosen.quizzes.index'))->assertRedirect(route('dosen.pending'));
        $this->get(route('dosen.students.index'))->assertRedirect(route('dosen.pending'));

        // Belum disetujui: tidak muncul sebagai pilihan siswa.
        $this->post(route('logout'));
        $this->get('/register')->assertDontSee('SMA Harapan Bangsa');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.lecturers.index'))->assertOk()
            ->assertSee('Menunggu persetujuan (1)')->assertSee('SMA Harapan Bangsa');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('1 pendaftaran dosen/guru menunggu persetujuan');

        $this->actingAs($admin)->post(route('admin.lecturers.approve', $guru))->assertSessionHas('status');
        $this->assertFalse($guru->fresh()->isPendingApproval());
        Notification::assertSentTo($guru, LecturerApprovedNotification::class);

        $this->actingAs($guru->fresh())->get(route('dosen.quizzes.index'))->assertOk();
        $this->actingAs($guru->fresh())->get(route('dosen.pending'))->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_reject_pending_teacher_but_not_active_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = User::factory()->pendingApproval()->create();
        $active = $this->makeTeacher();

        $this->actingAs($admin)->delete(route('admin.lecturers.reject', $active))->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.lecturers.reject', $pending))->assertSessionHas('status');

        $this->assertNull($pending->fresh());
        $this->assertNotNull($active->fresh());
    }

    public function test_non_admin_cannot_approve(): void
    {
        $pending = User::factory()->pendingApproval()->create();
        $dosen = $this->makeTeacher();

        $this->actingAs($dosen)->post(route('admin.lecturers.approve', $pending))->assertForbidden();
        $this->assertTrue($pending->fresh()->isPendingApproval());
    }

    public function test_student_registers_with_several_teachers_and_kelas_from_their_lists(): void
    {
        $uns = $this->makeTeacher(['A', 'B', 'C'], ['name' => 'Dosen UNS']);
        $smk = $this->makeTeacher(['X TKJ 1'], ['name' => 'Guru SMK']);
        $lain = $this->makeTeacher(['Z'], ['name' => 'Guru Lain']);

        $this->get('/register')->assertOk()->assertSee('Dosen UNS')->assertSee('Guru SMK');

        // Kelas milik dosen yang tidak dipilih ditolak.
        $this->post('/register', $this->registerPayload([
            'role' => 'mahasiswa', 'nim' => 'S001', 'teachers' => [$uns->id, $smk->id], 'kelas' => 'Z',
        ]))->assertSessionHasErrors('kelas');
        $this->assertGuest();

        $this->post('/register', $this->registerPayload([
            'role' => 'mahasiswa', 'nim' => 'S001', 'teachers' => [$uns->id, $smk->id], 'kelas' => 'X TKJ 1',
        ]))->assertRedirect('/dashboard');

        $siswa = User::where('email', 'baru@contoh.test')->firstOrFail();
        $this->assertSame('mahasiswa', $siswa->role);
        $this->assertSame('X TKJ 1', $siswa->kelas);
        $this->assertEqualsCanonicalizing([$uns->id, $smk->id], $siswa->teachers->pluck('id')->all());
        $this->assertFalse($siswa->isPendingApproval());
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'X TKJ 1'], $siswa->kelasOptions());
    }

    public function test_student_cannot_pick_pending_teacher_or_admin_role(): void
    {
        $pending = User::factory()->pendingApproval()->create();

        $this->post('/register', $this->registerPayload([
            'role' => 'mahasiswa', 'nim' => 'S002', 'teachers' => [$pending->id], 'kelas' => 'A',
        ]))->assertSessionHasErrors('teachers.0');

        $this->post('/register', $this->registerPayload(['role' => 'admin', 'nim' => 'S002']))
            ->assertSessionHasErrors('role');

        $this->assertSame(0, User::where('email', 'baru@contoh.test')->count());
    }

    public function test_teacher_without_classes_lets_student_register_without_kelas(): void
    {
        $guru = $this->makeTeacher([]);

        $this->post('/register', $this->registerPayload([
            'role' => 'mahasiswa', 'nim' => 'S003', 'teachers' => [$guru->id],
        ]))->assertRedirect('/dashboard');

        $this->assertNull(User::where('nim', 'S003')->value('kelas'));
    }

    public function test_teacher_sees_and_manages_only_own_students(): void
    {
        $a = $this->makeTeacher();
        $b = $this->makeTeacher();
        $milikA = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'NA001', 'kelas' => 'A']);
        $milikB = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'NB001', 'kelas' => 'B']);
        $this->linkStudents($a, $milikA);
        $this->linkStudents($b, $milikB);

        $this->actingAs($a)->get(route('dosen.students.index'))->assertOk()
            ->assertSee('NA001')->assertDontSee('NB001');

        $this->actingAs($a)->put(route('dosen.students.update', $milikB), [
            'name' => 'X', 'nim' => 'NB001', 'kelas' => 'B', 'email' => $milikB->email,
        ])->assertForbidden();
        $this->actingAs($a)->put(route('dosen.students.password', $milikB), [
            'confirm_email' => $milikB->email, 'password' => 'baru123', 'password_confirmation' => 'baru123',
        ])->assertForbidden();
        $this->actingAs($a)->delete(route('dosen.students.destroy', $milikB), ['confirm_email' => $milikB->email])
            ->assertForbidden();
        $this->assertNotNull($milikB->fresh());

        // Admin tetap melihat semua siswa.
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('dosen.students.index'))->assertSee('NA001')->assertSee('NB001');
    }

    public function test_deleting_shared_student_only_detaches_from_teacher(): void
    {
        $a = $this->makeTeacher();
        $b = $this->makeTeacher();
        $shared = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'NS001', 'kelas' => 'A']);
        $this->linkStudents($a, $shared);
        $this->linkStudents($b, $shared);

        $this->actingAs($a)->delete(route('dosen.students.destroy', $shared), ['confirm_email' => $shared->email])
            ->assertSessionHas('status');

        $this->assertNotNull($shared->fresh());
        $this->assertSame([$b->id], $shared->fresh()->teachers->pluck('id')->all());

        // Dosen terakhir menghapus: akun benar-benar terhapus.
        $this->actingAs($b)->delete(route('dosen.students.destroy', $shared), ['confirm_email' => $shared->email]);
        $this->assertNull($shared->fresh());
    }

    public function test_enrollment_by_nim_only_finds_own_students(): void
    {
        $a = $this->makeTeacher();
        $b = $this->makeTeacher();
        $milikB = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'NB009', 'kelas' => 'A']);
        $this->linkStudents($b, $milikB);
        $course = \App\Models\Course::create(['dosen_id' => $a->id, 'name' => 'MK A']);
        $period = \App\Models\AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil', 'is_active' => true]);

        $this->actingAs($a)->post(route('dosen.enrollments.storeNims', $course), [
            'academic_period_id' => $period->id, 'nims' => 'NB009',
        ])->assertSessionHasErrors('nims');

        $this->assertSame(0, \App\Models\CourseEnrollment::count());
    }

    public function test_student_quiz_list_shows_only_chosen_teachers_quizzes(): void
    {
        $a = $this->makeTeacher();
        $b = $this->makeTeacher();
        $siswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'S010', 'kelas' => 'A']);
        $this->linkStudents($a, $siswa);

        foreach ([[$a, 'Kuis Dosen A'], [$b, 'Kuis Dosen B']] as [$t, $title]) {
            Quiz::create(['dosen_id' => $t->id, 'title' => $title, 'duration_minutes' => 10, 'max_attempts' => 1,
                'is_published' => true, 'is_public' => true]);
        }

        $this->actingAs($siswa)->get(route('mahasiswa.quizzes.index'))->assertOk()
            ->assertSee('Kuis Dosen A')->assertDontSee('Kuis Dosen B');
    }

    public function test_student_adds_and_removes_teachers_but_keeps_at_least_one(): void
    {
        $a = $this->makeTeacher(['A'], ['name' => 'Dosen Satu']);
        $b = $this->makeTeacher(['X'], ['name' => 'Dosen Dua']);
        $siswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'S020', 'kelas' => 'A']);
        $this->linkStudents($a, $siswa);

        $this->actingAs($siswa)->get(route('profile.edit'))->assertOk()
            ->assertSee('Dosen / Guru Saya')->assertSee('Dosen Dua');

        $this->actingAs($siswa)->delete(route('profile.teachers.remove', $a))->assertSessionHasErrors('teacher_id');

        $this->actingAs($siswa)->post(route('profile.teachers.add'), ['teacher_id' => $b->id])->assertSessionHas('status');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $siswa->teachers()->pluck('users.id')->all());

        $this->actingAs($siswa)->delete(route('profile.teachers.remove', $a))->assertSessionHas('status');
        $this->assertSame([$b->id], $siswa->teachers()->pluck('users.id')->all());

        // Kelas lama (A) tetap boleh disimpan walau tidak ada di daftar dosen baru.
        $this->actingAs($siswa)->patch(route('profile.update'), [
            'name' => $siswa->name, 'email' => $siswa->email, 'nim' => 'S020', 'kelas' => 'A',
        ])->assertSessionHasNoErrors();
        $this->actingAs($siswa)->patch(route('profile.update'), [
            'name' => $siswa->name, 'email' => $siswa->email, 'nim' => 'S020', 'kelas' => 'Q',
        ])->assertSessionHasErrors('kelas');
    }

    public function test_teacher_manages_own_class_list(): void
    {
        $a = $this->makeTeacher(['A']);
        $b = $this->makeTeacher(['B']);

        $this->actingAs($a)->get(route('dosen.classes.index'))->assertOk()->assertSee('Kelas Saya');
        $this->actingAs($a)->post(route('dosen.classes.store'), ['name' => 'X IPA 1'])->assertSessionHasNoErrors();
        $this->actingAs($a)->post(route('dosen.classes.store'), ['name' => 'X IPA 1'])->assertSessionHasErrors('name');
        $this->assertSame(['A', 'X IPA 1'], $a->kelasOptions());

        $milikB = TeacherClass::where('teacher_id', $b->id)->first();
        $this->actingAs($a)->delete(route('dosen.classes.destroy', $milikB))->assertNotFound();

        $milikA = TeacherClass::where('teacher_id', $a->id)->where('name', 'A')->first();
        $this->actingAs($a)->delete(route('dosen.classes.destroy', $milikA))->assertSessionHas('status');
        $this->assertSame(['X IPA 1'], $a->kelasOptions());
    }

    public function test_import_links_new_and_existing_students_to_importing_teacher(): void
    {
        $guru = $this->makeTeacher(['X TKJ 1']);
        $lama = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'S100', 'email' => 'lama@contoh.test', 'kelas' => 'X TKJ 1']);

        $import = new \App\Imports\StudentsImport($guru->kelasOptions(), $guru);
        $import->collection(collect([
            ['Siswa Baru', 'S101', 'x tkj 1', 'baru1@contoh.test'],
            ['Siswa Lama', 'S100', 'X TKJ 1', 'LAMA@contoh.test'],
            ['Kelas Salah', 'S102', 'A', 'salah@contoh.test'],
        ]));

        $this->assertSame([['name' => 'Siswa Baru', 'nim' => 'S101', 'kelas' => 'X TKJ 1', 'email' => 'baru1@contoh.test']], $import->parsed);
        $this->assertSame([$lama->id], $import->existingIds);
        $this->assertCount(1, $import->skipped);
    }

    public function test_migration_backfills_existing_teachers_and_students(): void
    {
        $migration = require database_path('migrations/2026_10_16_000008_add_teacher_roles_and_links.php');
        $migration->down();

        $now = now();
        $dosenId = DB::table('users')->insertGetId(['name' => 'Dosen Lama', 'email' => 'd@x.test', 'password' => 'x', 'role' => 'dosen', 'created_at' => $now, 'updated_at' => $now]);
        $adminId = DB::table('users')->insertGetId(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x', 'role' => 'admin', 'created_at' => $now, 'updated_at' => $now]);
        foreach (['m1', 'm2'] as $m) {
            DB::table('users')->insert(['name' => $m, 'email' => "$m@x.test", 'password' => 'x', 'role' => 'mahasiswa', 'nim' => $m, 'kelas' => 'A', 'created_at' => $now, 'updated_at' => $now]);
        }

        $migration->up();

        $this->assertNotNull(User::find($dosenId)->approved_at);
        $this->assertNotNull(User::find($adminId)->approved_at);
        $this->assertSame(['A', 'B', 'C'], User::find($dosenId)->kelasOptions());
        $this->assertSame(2, DB::table('student_teacher')->where('teacher_id', $dosenId)->count());
        $this->assertSame(0, DB::table('student_teacher')->where('teacher_id', $adminId)->count());
    }
}
