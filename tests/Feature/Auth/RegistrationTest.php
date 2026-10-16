<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    /**
     * Diperbaiki: form register di aplikasi ini sudah di-custom, wajib isi
     * NIM & Kelas (khusus mahasiswa -- satu-satunya jalur registrasi publik)
     * selain Nama/Email/Password bawaan Breeze. Test asli bawaan Breeze
     * cuma kirim Nama/Email/Password sehingga validasi NIM/Kelas gagal dan
     * user tidak pernah ter-buat -- itu sebabnya test lama ini gagal, BUKAN
     * karena ada bug di form registernya.
     */
    public function test_new_users_can_register(): void
    {
        $teacher = $this->makeTeacher();

        $response = $this->post('/register', [
            'role' => 'mahasiswa',
            'teachers' => [$teacher->id],
            'name' => 'Test User',
            'nim' => '2024999999',
            'kelas' => 'A',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'nim' => '2024999999',
            'kelas' => 'A',
            'role' => 'mahasiswa',
        ]);
        $this->assertSame([$teacher->id], \App\Models\User::where('email', 'test@example.com')->first()->teachers->pluck('id')->all());
    }

    public function test_registration_fails_without_required_student_fields(): void
    {
        $response = $this->post('/register', [
            'role' => 'mahasiswa',
            'name' => 'Test User Tanpa NIM',
            'email' => 'tanpa-nim@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['nim', 'teachers']);
        $this->assertGuest();
    }
}
