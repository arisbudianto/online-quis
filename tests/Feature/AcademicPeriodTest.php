<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_label_format(): void
    {
        $p = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);

        $this->assertSame('2026/2027 Ganjil', $p->label);
    }

    public function test_admin_can_open_and_create_period_and_first_one_becomes_active(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.periods.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.periods.store'), ['year_start' => 2026, 'semester' => 'ganjil'])
            ->assertSessionHasNoErrors();

        $p = AcademicPeriod::firstOrFail();
        $this->assertTrue($p->is_active, 'Periode pertama otomatis aktif.');

        $this->actingAs($admin)->get(route('admin.periods.index'))->assertSee('2026/2027 Ganjil');
    }

    public function test_duplicate_period_is_rejected(): void
    {
        AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);

        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), ['year_start' => 2026, 'semester' => 'ganjil'])
            ->assertSessionHasErrors('semester');

        $this->assertSame(1, AcademicPeriod::count());
    }

    public function test_unknown_semester_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), ['year_start' => 2026, 'semester' => 'pendek'])
            ->assertSessionHasErrors('semester');
    }

    public function test_only_one_period_is_active(): void
    {
        $a = AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap', 'is_active' => true]);
        $b = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);

        $this->actingAs($this->admin())->post(route('admin.periods.activate', $b));

        $this->assertFalse($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
        $this->assertSame(1, AcademicPeriod::where('is_active', true)->count());
    }

    public function test_period_used_by_quiz_cannot_be_deleted(): void
    {
        $p = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);
        $dosen = User::factory()->create(['role' => 'dosen']);
        Quiz::create([
            'dosen_id' => $dosen->id, 'academic_period_id' => $p->id, 'title' => 'K',
            'duration_minutes' => 10, 'max_attempts' => 1,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.periods.destroy', $p))
            ->assertSessionHasErrors('period');

        $this->assertNotNull($p->fresh());
    }

    public function test_unused_period_can_be_deleted(): void
    {
        $p = AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);

        $this->actingAs($this->admin())->delete(route('admin.periods.destroy', $p));

        $this->assertNull($p->fresh());
    }

    public function test_latest_first_ordering(): void
    {
        AcademicPeriod::create(['year_start' => 2025, 'semester' => 'ganjil']);
        AcademicPeriod::create(['year_start' => 2026, 'semester' => 'ganjil']);
        AcademicPeriod::create(['year_start' => 2025, 'semester' => 'genap']);

        $labels = AcademicPeriod::latestFirst()->get()->pluck('label')->all();

        $this->assertSame(['2026/2027 Ganjil', '2025/2026 Genap', '2025/2026 Ganjil'], $labels);
    }

    public function test_dosen_and_mahasiswa_cannot_manage_periods(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526001', 'kelas' => 'A']);

        $this->actingAs($dosen)->get(route('admin.periods.index'))->assertForbidden();
        $this->actingAs($mhs)->post(route('admin.periods.store'), ['year_start' => 2026, 'semester' => 'ganjil'])->assertForbidden();
        $this->assertSame(0, AcademicPeriod::count());
    }
}
