<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountCredentialsNotification;
use App\Support\RoleRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Sejak 16-10-2026 pendaftar memilih peran: mahasiswa/siswa (memilih
     * dosen/guru dan kelas) atau dosen/guru. Dosen/guru yang mendaftar sendiri
     * baru bisa memakai menu dosen setelah disetujui admin.
     */
    public function store(Request $request): RedirectResponse
    {
        // Peran mahasiswa/siswa atau dosen/guru. Dosen/guru menunggu
        // persetujuan admin (lihat RoleMiddleware).
        $data = RoleRegistration::validate($request->all(), [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $plainPassword = $request->password;

        $user = RoleRegistration::create($data, $request->email, Hash::make($plainPassword));

        event(new Registered($user));

        // Kirim email berisi kredensial login (email + password) ke mahasiswa.
        // Kalau .env MAIL_MAILER belum dikonfigurasi dengan benar, ini akan gagal diam-diam
        // dicatat di storage/logs/laravel.log tanpa menghentikan proses registrasi.
        try {
            $user->notify(new AccountCredentialsNotification($plainPassword));
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
