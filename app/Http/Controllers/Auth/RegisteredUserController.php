<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountCredentialsNotification;
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
     * Catatan: registrasi publik ini khusus untuk MAHASISWA.
     * Akun dosen dibuat manual oleh admin (lewat tinker/seeder), bukan lewat form ini,
     * supaya tidak sembarang orang bisa mendaftar sebagai dosen.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', 'unique:'.User::class],
            'kelas' => ['required', 'string', 'in:A,B,C'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $plainPassword = $request->password;

        $user = User::create([
            'name' => $request->name,
            'nim' => $request->nim,
            'kelas' => $request->kelas,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'role' => 'mahasiswa',
        ]);

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
