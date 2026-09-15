<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_mahasiswa_cannot_access_dosen_routes(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000001', 'kelas' => 'A']);

        $this->actingAs($mahasiswa)
            ->get(route('dosen.quizzes.index'))
            ->assertForbidden();
    }

    public function test_dosen_cannot_access_admin_routes(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($dosen)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_mahasiswa_cannot_access_admin_routes(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000002', 'kelas' => 'B']);

        $this->actingAs($mahasiswa)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_mahasiswa_only_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('mahasiswa.quizzes.index'))
            ->assertForbidden();
    }

    /**
     * Admin SENGAJA tetap bisa akses route dosen (middleware 'role:dosen,admin')
     * -- ini BUKAN bug, ini desain yang disepakati supaya admin bisa
     * merangkap jadi dosen kalau perlu.
     */
    public function test_admin_can_access_dosen_routes_by_design(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dosen.quizzes.index'))
            ->assertOk();
    }

    public function test_dosen_can_access_dosen_routes(): void
    {
        $dosen = User::factory()->create(['role' => 'dosen']);

        $this->actingAs($dosen)
            ->get(route('dosen.quizzes.index'))
            ->assertOk();
    }

    public function test_mahasiswa_can_access_mahasiswa_routes(): void
    {
        $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'nim' => '2024000003', 'kelas' => 'C']);

        $this->actingAs($mahasiswa)
            ->get(route('mahasiswa.quizzes.index'))
            ->assertOk();
    }
}
