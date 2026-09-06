<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Tampilkan form edit profil. Field NIM/Kelas cuma ditampilkan untuk
     * role mahasiswa -- ditangani langsung di view lewat @if(auth()->user()->role).
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update profil (Nama, Email, dan khusus mahasiswa: NIM + Kelas).
     *
     * NIM/Kelas HANYA divalidasi & disimpan kalau role user = mahasiswa --
     * dosen/admin tidak pernah mengirim field ini (tidak ada di form-nya),
     * dan kalaupun ada yang mencoba mengirim manual, tetap diabaikan
     * (tidak masuk ke $rules sama sekali untuk role selain mahasiswa).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isMahasiswa = $user->role === 'mahasiswa';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ];

        if ($isMahasiswa) {
            $rules['nim'] = [
                'required', 'string', 'max:50',
                Rule::unique('users', 'nim')->ignore($user->id),
            ];
            $rules['kelas'] = ['required', 'in:A,B,C'];
        }

        $validated = $request->validate($rules);

        $emailChanged = $user->email !== $validated['email'];

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($isMahasiswa) {
            $user->nim = $validated['nim'];
            $user->kelas = $validated['kelas'];
        }

        // Cuma reset verifikasi kalau EMAIL benar-benar berubah -- ganti
        // Nama/NIM/Kelas saja TIDAK memengaruhi status verifikasi sama sekali.
        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();

            return Redirect::route('profile.edit')->with('status', 'Email berhasil diubah menjadi ' . $user->email . '. Kami sudah mengirim link verifikasi baru ke email tersebut -- silakan cek & klik link itu untuk mengaktifkan kembali akun Anda.');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
