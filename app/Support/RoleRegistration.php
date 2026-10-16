<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bersama untuk pendaftaran lewat formulir dan lewat Google
 * (keputusan pemilik 16-10-2026):
 * - Peran "mahasiswa": NIM/NIS wajib, memilih satu atau lebih dosen/guru
 *   yang sudah disetujui, dan kelas dari gabungan daftar kelas dosen itu.
 * - Peran "dosen": instansi wajib; akun menunggu persetujuan admin.
 */
class RoleRegistration
{
    public const ROLES = ['mahasiswa', 'dosen'];

    /**
     * Dosen yang bisa dipilih siswa, beserta kelasnya.
     *
     * @return array<int, array{id: int, name: string, institution: ?string, classes: array<int, string>}>
     */
    public static function teacherOptions(): array
    {
        return User::approvedTeachers()
            ->with(['teacherClasses' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'institution'])
            ->map(fn (User $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'institution' => $t->institution,
                'classes' => $t->teacherClasses->pluck('name')->all(),
            ])
            ->all();
    }

    /**
     * Validasi field peran. Field akun (email/password) divalidasi pemanggil.
     *
     * @return array{role: string, name: string, nim: ?string, kelas: ?string, institution: ?string, teachers: array<int, int>}
     */
    public static function validate(array $input, array $extraRules = []): array
    {
        $role = $input['role'] ?? null;

        $rules = $extraRules + [
            'role' => ['required', Rule::in(self::ROLES)],
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($role === 'dosen') {
            $rules['institution'] = ['required', 'string', 'max:255'];
        } else {
            $rules['nim'] = ['required', 'string', 'max:50', Rule::unique('users', 'nim')];
            $rules['teachers'] = ['required', 'array', 'min:1', 'max:20'];
            $rules['teachers.*'] = ['integer', 'distinct', Rule::exists('users', 'id')
                ->where('role', 'dosen')->whereNotNull('approved_at')];
        }

        $messages = [
            'role.required' => 'Pilih daftar sebagai mahasiswa/siswa atau dosen/guru.',
            'teachers.required' => 'Pilih minimal satu dosen/guru.',
            'teachers.*.exists' => 'Dosen/guru yang dipilih tidak tersedia.',
            'institution.required' => 'Isi nama kampus, sekolah, atau instansi Anda.',
        ];

        $data = Validator::make($input, $rules, $messages, ['nim' => 'NIM/NIS'])->validate();

        $result = [
            'role' => $data['role'],
            'name' => $data['name'],
            'nim' => null,
            'kelas' => null,
            'institution' => null,
            'teachers' => [],
        ];

        if ($data['role'] === 'dosen') {
            $result['institution'] = trim($data['institution']);

            return $result;
        }

        $teacherIds = array_values(array_unique(array_map('intval', $data['teachers'])));
        $options = User::kelasOptionsFor($teacherIds);
        $kelas = trim((string) ($input['kelas'] ?? ''));

        if ($options !== [] && !in_array($kelas, $options, true)) {
            throw ValidationException::withMessages([
                'kelas' => $kelas === ''
                    ? 'Pilih kelas.'
                    : 'Kelas tidak ada di daftar kelas dosen/guru yang dipilih.',
            ]);
        }

        $result['nim'] = trim($data['nim']);
        $result['kelas'] = $options === [] ? null : $kelas;
        $result['teachers'] = $teacherIds;

        return $result;
    }

    /** Buat akun dari data hasil validate() ditambah email dan password (sudah di-hash). */
    public static function create(array $data, string $email, string $hashedPassword, array $extra = []): User
    {
        return DB::transaction(function () use ($data, $email, $hashedPassword, $extra) {
            $user = User::create([
                'name' => $data['name'],
                'nim' => $data['nim'],
                'kelas' => $data['kelas'],
                'email' => $email,
                'password' => $hashedPassword,
                'role' => $data['role'],
                'institution' => $data['institution'],
            ]);

            if ($extra) {
                $user->forceFill($extra)->save();
            }

            if ($data['teachers']) {
                $user->teachers()->sync($data['teachers']);
            }

            return $user;
        });
    }
}
