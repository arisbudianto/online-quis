<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'client-123',
            'services.google.client_secret' => 'secret-xyz',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    private function fakeGoogle(array $profile, int $tokenStatus = 200): void
    {
        Http::fake([
            GoogleOAuth::TOKEN_URL => Http::response(
                $tokenStatus === 200 ? ['access_token' => 'at-1', 'id_token' => 'x'] : ['error' => 'invalid_grant'],
                $tokenStatus
            ),
            GoogleOAuth::USERINFO_URL => Http::response($profile + ['email_verified' => true]),
        ]);
    }

    /** Jalankan alur redirect lalu callback dengan state yang benar. */
    private function googleCallback(array $query = ['code' => 'kode-1'])
    {
        $redirect = $this->get(route('google.redirect'));
        $redirect->assertRedirect();
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $params);

        return $this->get(route('google.callback', $query + ['state' => $params['state']]));
    }

    public function test_redirect_points_to_google_with_state_and_scopes(): void
    {
        $response = $this->get(route('google.redirect'));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(GoogleOAuth::AUTH_URL . '?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('client-123', $q['client_id']);
        $this->assertSame('http://localhost/auth/google/callback', $q['redirect_uri']);
        $this->assertSame('openid email profile', $q['scope']);
        $this->assertSame(40, strlen($q['state']));
        $this->assertSame($q['state'], session('google_oauth_state'));
    }

    public function test_routes_and_buttons_hidden_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Masuk atau daftar dengan Google');
        $this->get(route('register'))->assertOk()->assertDontSee('Daftar dengan Google');
    }

    public function test_buttons_shown_when_configured(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk atau daftar dengan Google')->assertSee('Lupa password?');
        $this->get(route('register'))->assertOk()->assertSee('Daftar dengan Google');
        $this->get(route('password.request'))->assertOk()->assertSee('Masuk dengan Google tanpa password');
    }

    public function test_wrong_state_is_rejected_without_calling_google(): void
    {
        Http::fake();
        $this->get(route('google.redirect'));

        $this->get(route('google.callback', ['code' => 'x', 'state' => 'palsu']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_new_google_user_completes_nim_and_kelas_and_is_verified(): void
    {
        $this->fakeGoogle(['sub' => 'g-1', 'email' => 'Budi@Student.UNS.ac.id', 'name' => 'Budi Santoso']);

        $this->googleCallback()->assertRedirect(route('google.complete'));
        $this->assertGuest();

        $this->get(route('google.complete'))->assertOk()
            ->assertSee('budi@student.uns.ac.id')->assertSee('Budi Santoso');

        $this->post(route('google.complete.store'), ['name' => 'Budi Santoso', 'nim' => 'K3526010', 'kelas' => 'B'])
            ->assertRedirect('/dashboard');

        $user = User::where('email', 'budi@student.uns.ac.id')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-1', $user->google_id);
        $this->assertSame('mahasiswa', $user->role);
        $this->assertSame('K3526010', $user->nim);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull(session('google_pending'));
    }

    public function test_complete_form_validates_nim_and_kelas(): void
    {
        User::factory()->create(['role' => 'mahasiswa', 'nim' => 'K3526010']);
        $this->fakeGoogle(['sub' => 'g-1', 'email' => 'baru@gmail.com', 'name' => 'Baru']);
        $this->googleCallback();

        $this->post(route('google.complete.store'), ['name' => 'Baru', 'nim' => 'K3526010', 'kelas' => 'D'])
            ->assertSessionHasErrors(['nim', 'kelas']);

        $this->assertSame(0, User::where('email', 'baru@gmail.com')->count());
    }

    public function test_complete_without_google_session_goes_to_login(): void
    {
        $this->get(route('google.complete'))->assertRedirect(route('login'));
        $this->post(route('google.complete.store'), ['name' => 'X', 'nim' => 'K1', 'kelas' => 'A'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, User::count());
    }

    public function test_existing_account_with_same_email_is_linked_and_verified(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'mahasiswa', 'email' => 'ani@student.uns.ac.id']);
        $this->fakeGoogle(['sub' => 'g-ani', 'email' => 'ANI@student.uns.ac.id', 'name' => 'Ani']);

        $this->googleCallback()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('g-ani', $user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_linked_account_signs_in_by_google_id_even_if_google_email_changed(): void
    {
        $user = User::factory()->create(['role' => 'dosen', 'email' => 'dosen@staff.uns.ac.id']);
        $user->forceFill(['google_id' => 'g-dosen'])->save();
        $this->fakeGoogle(['sub' => 'g-dosen', 'email' => 'alamat-baru@gmail.com', 'name' => 'Dosen']);

        $this->googleCallback()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('dosen@staff.uns.ac.id', $user->fresh()->email, 'email akun tidak diubah');
        $this->assertSame(1, User::count());
    }

    public function test_email_linked_to_other_google_account_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ani@student.uns.ac.id']);
        $user->forceFill(['google_id' => 'g-asli'])->save();
        $this->fakeGoogle(['sub' => 'g-lain', 'email' => 'ani@student.uns.ac.id', 'name' => 'Ani']);

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Http::fake([
            GoogleOAuth::TOKEN_URL => Http::response(['access_token' => 'at-1']),
            GoogleOAuth::USERINFO_URL => Http::response(['sub' => 'g-1', 'email' => 'x@contoh.test', 'email_verified' => false]),
        ]);
        User::factory()->create(['email' => 'x@contoh.test']);

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'x@contoh.test')->value('google_id'));
    }

    public function test_google_error_or_cancel_returns_to_login(): void
    {
        $this->fakeGoogle(['sub' => 'g-1', 'email' => 'a@b.test'], 400);
        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->googleCallback(['error' => 'access_denied'])->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_logged_in_unverified_user_verifies_with_matching_google_email(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'mahasiswa', 'email' => 'cici@student.uns.ac.id']);
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verifikasi dengan Google');

        $this->fakeGoogle(['sub' => 'g-cici', 'email' => 'cici@student.uns.ac.id', 'name' => 'Cici']);
        $this->googleCallback()->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('g-cici', $user->google_id);
    }

    public function test_logged_in_user_with_different_google_email_stays_unverified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'cici@student.uns.ac.id']);
        $this->actingAs($user);
        $this->fakeGoogle(['sub' => 'g-x', 'email' => 'orang.lain@gmail.com', 'name' => 'Lain']);

        $this->googleCallback()
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('google');

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->google_id);
    }

    public function test_google_created_account_can_set_password_via_forgot_password(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->fakeGoogle(['sub' => 'g-1', 'email' => 'dodi@gmail.com', 'name' => 'Dodi']);
        $this->googleCallback();
        $this->post(route('google.complete.store'), ['name' => 'Dodi', 'nim' => 'K3526011', 'kelas' => 'A']);
        $this->post(route('logout'));

        $this->post(route('password.email'), ['email' => 'dodi@gmail.com'])->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            User::where('email', 'dodi@gmail.com')->first(),
            \Illuminate\Auth\Notifications\ResetPassword::class
        );
    }
}
