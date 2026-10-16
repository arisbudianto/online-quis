<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * Update data dasar mahasiswa (nama, NIM, kelas, email).
     */
    public function update(Request $request, User $student)
    {
        $this->ensureCanManage($request, $student);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nim' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nim')->ignore($student->id)],
            'kelas' => ['nullable', Rule::in(array_values(array_unique(array_filter(
                array_merge($request->user()->kelasOptions(), [$student->kelas])
            ))))],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
        ]);

        $student->update($validated);

        return back()->with('status', "Data mahasiswa {$student->name} berhasil diperbarui.");
    }

    /**
     * Hapus akun mahasiswa. Wajib mengetik ulang email mahasiswa
     * yang bersangkutan sebagai konfirmasi sebelum data dihapus.
     */
    public function destroy(Request $request, User $student)
    {
        $this->ensureCanManage($request, $student);

        $request->validate([
            'confirm_email' => ['required', 'email'],
        ]);

        if (strcasecmp($request->input('confirm_email'), $student->email) !== 0) {
            return back()
                ->withErrors(['confirm_email' => "Email konfirmasi tidak cocok dengan email mahasiswa ({$student->email}). Mahasiswa TIDAK dihapus."])
                ->withInput();
        }

        $name = $student->name;
        $viewer = $request->user();

        // Siswa yang juga memilih dosen lain tidak dihapus akunnya; cukup
        // dilepas dari daftar dosen ini.
        if ($viewer->role === 'dosen' && $student->teachers()->where('users.id', '!=', $viewer->id)->exists()) {
            $student->teachers()->detach($viewer->id);

            return back()->with('status', "{$name} dikeluarkan dari daftar siswa Anda. Akunnya tetap ada karena juga terdaftar pada dosen/guru lain.");
        }

        $student->delete();

        return back()->with('status', "Mahasiswa {$name} berhasil dihapus.");
    }

    /**
     * Ubah password mahasiswa secara manual oleh dosen. Wajib mengetik
     * ulang email mahasiswa yang bersangkutan sebagai konfirmasi.
     */
    public function updatePassword(Request $request, User $student)
    {
        $this->ensureCanManage($request, $student);

        $validated = $request->validate([
            'confirm_email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (strcasecmp($validated['confirm_email'], $student->email) !== 0) {
            return back()->withErrors([
                'confirm_email' => "Email konfirmasi tidak cocok dengan email mahasiswa ({$student->email}). Password TIDAK diubah.",
            ]);
        }

        $student->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', "Password mahasiswa {$student->name} berhasil diubah.");
    }

    /**
     * Hanya akun mahasiswa yang memilih dosen ini (atau admin) yang boleh
     * disunting, dihapus, atau diubah password-nya.
     */
    private function ensureCanManage(Request $request, User $student): void
    {
        abort_unless($student->role === 'mahasiswa', 404);
        // Dosen hanya mengelola siswa yang memilihnya; admin semua siswa.
        abort_unless($request->user()->canManageStudent($student), 403, 'Mahasiswa ini bukan siswa Anda.');
    }
}
