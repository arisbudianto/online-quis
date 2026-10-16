<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            // Kelas dipilih dari gabungan daftar kelas dosen/guru siswa ini.
            // Kelas lama yang tidak ada di daftar tetap boleh dipertahankan.
            $options = $user->kelasOptions();
            $rules['kelas'] = $options === []
                ? ['nullable', 'string', 'max:30']
                : ['required', Rule::in(array_values(array_unique(array_filter(array_merge($options, [$user->kelas])))))];
        }

        $validated = $request->validate($rules);

        $emailChanged = $user->email !== $validated['email'];

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($isMahasiswa) {
            $user->nim = $validated['nim'];
            $user->kelas = $validated['kelas'] ?? $user->kelas;
        }

        // Cuma reset verifikasi kalau EMAIL benar-benar berubah -- ganti
        // Nama/NIM/Kelas saja TIDAK memengaruhi status verifikasi sama sekali.
        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            if (!$user->sendEmailVerificationNotification()) {
                return Redirect::route('profile.edit')->with('status', 'Email berhasil diubah menjadi ' . $user->email
                    . ', tetapi email verifikasi tidak bisa dikirim ke alamat itu. Periksa kembali alamatnya atau gunakan Verifikasi dengan Google.');
            }

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

    /** Siswa menambah dosen/guru. */
    public function addTeacher(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);

        $data = $request->validate([
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'dosen')->whereNotNull('approved_at')],
        ], ['teacher_id.exists' => 'Dosen/guru tidak tersedia.']);

        $user->teachers()->syncWithoutDetaching([(int) $data['teacher_id']]);

        return Redirect::route('profile.edit')->with('status', 'Dosen/guru ditambahkan.');
    }

    /** Siswa melepas dosen/guru; minimal satu harus tersisa. */
    public function removeTeacher(Request $request, User $teacher): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'mahasiswa', 403);

        if ($user->teachers()->count() <= 1) {
            return Redirect::route('profile.edit')->withErrors(['teacher_id' => 'Minimal satu dosen/guru harus tetap dipilih.']);
        }

        $user->teachers()->detach($teacher->id);

        return Redirect::route('profile.edit')->with('status', "{$teacher->name} dilepas dari daftar dosen/guru Anda.");
    }
}
