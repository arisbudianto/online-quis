<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    private function failingMail(): void
    {
        Notification::shouldReceive('send')->andThrow(
            new \Symfony\Component\Mailer\Exception\UnexpectedResponseException('550 No Such User Here', 550)
        );
    }

    public function test_sent_link_lands_on_check_email_page(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@contoh.test']);

        $this->post('/forgot-password', ['email' => 'ADA@contoh.test'])->assertRedirect(route('password.result'));
        $this->get(route('password.result'))->assertOk()->assertSee('Cek email Anda')->assertSee('ada@contoh.test');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_lands_on_not_registered_page(): void
    {
        $this->post('/forgot-password', ['email' => 'tidakada@contoh.test'])->assertRedirect(route('password.result'));
        $this->get(route('password.result'))->assertOk()
            ->assertSee('Email belum terdaftar')->assertSee('Daftar akun baru');
    }

    public function test_rejected_recipient_lands_on_undeliverable_page_not_500(): void
    {
        User::factory()->create(['email' => 'admin@contoh.test']);
        $this->failingMail();

        $this->post('/forgot-password', ['email' => 'admin@contoh.test'])->assertRedirect(route('password.result'));
        $this->get(route('password.result'))->assertOk()
            ->assertSee('Email tidak bisa dikirim')->assertSee('minta dosen/guru Anda');
    }

    public function test_result_page_without_request_goes_back_to_form(): void
    {
        $this->get(route('password.result'))->assertRedirect(route('password.request'));
    }

    public function test_verification_email_failure_does_not_break_registration_or_resend(): void
    {
        $teacher = $this->makeTeacher();
        $this->failingMail();

        $this->post('/register', [
            'role' => 'mahasiswa', 'teachers' => [$teacher->id], 'kelas' => 'A',
            'name' => 'Siswa', 'nim' => 'S900', 'email' => 'mati@contoh.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->post(route('verification.send'))->assertRedirect();
        $this->assertNotNull(session('mail_error'));
    }

    public function test_server_error_page_is_friendly_indonesian(): void
    {
        config(['app.debug' => false]);
        \Illuminate\Support\Facades\Route::get('/_uji-500', fn () => throw new \RuntimeException('rusak'));

        $this->get('/_uji-500')->assertStatus(500)
            ->assertSee('Ada gangguan di server')->assertDontSee('rusak');
    }
}
