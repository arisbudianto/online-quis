<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\GoogleEmailNotVerified;
use App\Support\GoogleOAuth;
use App\Support\RoleRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Masuk, daftar, dan verifikasi email dengan akun Google.
 *
 * - Tamu, akun Google sudah terhubung: langsung masuk.
 * - Tamu, email Google sama dengan akun yang ada: akun dihubungkan,
 *   email ditandai terverifikasi, lalu masuk.
 * - Tamu, email belum terdaftar: pilih peran (mahasiswa/siswa atau
 *   dosen/guru) dan lengkapi data; email langsung terverifikasi.
 *   Dosen/guru tetap menunggu persetujuan admin.
 * - Sudah login tetapi email belum terverifikasi: email Google harus sama
 *   dengan email akun, lalu akun ditandai terverifikasi.
 *
 * Akun yang dibuat lewat Google memakai password acak; pemiliknya bisa
 * membuat password sendiri lewat "Lupa password".
 */
class GoogleAuthController extends Controller
{
    private const STATE_KEY = 'google_oauth_state';
    private const PENDING_KEY = 'google_pending';
    private const PENDING_TTL = 900; // detik

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(GoogleOAuth::enabled(), 404);

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away(GoogleOAuth::authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleOAuth::enabled(), 404);

        $expected = $request->session()->pull(self::STATE_KEY);
        $state = (string) $request->query('state', '');
        if (!is_string($expected) || $expected === '' || !hash_equals($expected, $state)) {
            return $this->fail($request, 'Sesi masuk dengan Google kedaluwarsa. Silakan coba lagi.');
        }

        if ($request->filled('error') || !$request->filled('code')) {
            return $this->fail($request, 'Masuk dengan Google dibatalkan.');
        }

        try {
            $google = GoogleOAuth::profileFromCode((string) $request->query('code'));
        } catch (GoogleEmailNotVerified $e) {
            return $this->fail($request, "Email Google {$e->email} belum diverifikasi oleh Google. Gunakan akun Google lain.");
        } catch (Throwable $e) {
            report($e);

            return $this->fail($request, 'Tidak bisa terhubung ke Google. Silakan coba lagi.');
        }

        return Auth::check()
            ? $this->verifyCurrentUser($request, $google)
            : $this->signIn($request, $google);
    }

    public function completeForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);
        if (!$pending) {
            return redirect()->route('login')->withErrors(['google' => 'Silakan masuk dengan Google terlebih dahulu.']);
        }

        return view('auth.google-complete', ['pending' => $pending]);
    }

    public function completeStore(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (!$pending) {
            return redirect()->route('login')->withErrors(['google' => 'Sesi pendaftaran Google kedaluwarsa. Silakan ulangi.']);
        }

        $data = RoleRegistration::validate($request->all());

        // Bisa saja email atau akun Google ini terdaftar di tab lain sejak tadi.
        $taken = User::whereRaw('LOWER(email) = ?', [$pending['email']])
            ->orWhere('google_id', $pending['sub'])
            ->exists();
        if ($taken) {
            $request->session()->forget(self::PENDING_KEY);

            return redirect()->route('login')->withErrors(['google' => 'Akun dengan email ini sudah terdaftar. Silakan masuk dengan Google.']);
        }

        $user = RoleRegistration::create($data, $pending['email'], Hash::make(Str::random(40)), [
            'google_id' => $pending['sub'],
            'email_verified_at' => now(),
        ]);

        $request->session()->forget(self::PENDING_KEY);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(RouteServiceProvider::HOME);
    }

    /** @param array{sub: string, email: string, name: string} $google */
    private function signIn(Request $request, array $google): RedirectResponse
    {
        $user = User::where('google_id', $google['sub'])->first()
            ?? User::whereRaw('LOWER(email) = ?', [$google['email']])->first();

        if (!$user) {
            $request->session()->put(self::PENDING_KEY, $google + ['expires' => now()->timestamp + self::PENDING_TTL]);

            return redirect()->route('google.complete');
        }

        if ($user->google_id && $user->google_id !== $google['sub']) {
            return $this->fail($request, 'Email ini sudah terhubung dengan akun Google lain. Masuk dengan email dan password.');
        }

        $this->linkAndVerify($user, $google['sub']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /** @param array{sub: string, email: string, name: string} $google */
    private function verifyCurrentUser(Request $request, array $google): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (strtolower($user->email) !== $google['email']) {
            return $this->fail($request, "Akun Google ({$google['email']}) berbeda dengan email akun Anda ({$user->email}). Pilih akun Google dengan email yang sama.");
        }

        if (User::where('google_id', $google['sub'])->where('id', '!=', $user->id)->exists()
            || ($user->google_id && $user->google_id !== $google['sub'])) {
            return $this->fail($request, 'Akun Google ini sudah terhubung dengan akun lain.');
        }

        $this->linkAndVerify($user, $google['sub']);

        return redirect()->intended(RouteServiceProvider::HOME)->with('status', 'Email berhasil diverifikasi dengan Google.');
    }

    private function linkAndVerify(User $user, string $sub): void
    {
        $wasUnverified = !$user->hasVerifiedEmail();

        $user->forceFill([
            'google_id' => $user->google_id ?: $sub,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($wasUnverified) {
            event(new Verified($user));
        }
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (!is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        $route = Auth::check() ? 'verification.notice' : 'login';

        if (Auth::check() && $request->user()->hasVerifiedEmail()) {
            $route = 'profile.edit';
        }

        return redirect()->route($route)->withErrors(['google' => $message]);
    }
}
