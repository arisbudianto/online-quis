<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * PERBAIKAN dari versi sebelumnya: versi lama mengambil nilai cookie
     * sesi yang SUDAH TERENKRIPSI dari response (lewat $response->headers
     * ->getCookies()), lalu mengirimkannya lagi lewat withCookie() -- yang
     * MENGENKRIPSI SEKALI LAGI secara otomatis. Hasilnya dobel-enkripsi,
     * cookie yang diterima request berikutnya gagal di-decrypt jadi ID
     * yang valid, sehingga Laravel menganggapnya sesi baru (persis seperti
     * "device berbeda" di mata sistem device-binding kita).
     *
     * Perbaikannya: ambil session ID MENTAH (belum dienkripsi) langsung
     * dari container, lalu serahkan ke withCookie() supaya Laravel yang
     * mengenkripsinya SEKALI dengan benar untuk request berikutnya.
     */
    protected function continueSession(): void
    {
        $sessionId = $this->app['session']->getId();

        $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** Dosen yang sudah disetujui dengan daftar kelasnya sendiri. */
    protected function makeTeacher(array $classes = ['A', 'B', 'C'], array $attrs = []): \App\Models\User
    {
        $teacher = \App\Models\User::factory()->create(array_merge(['role' => 'dosen'], $attrs));
        foreach ($classes as $c) {
            $teacher->teacherClasses()->create(['name' => $c]);
        }

        return $teacher;
    }

    /** Hubungkan siswa ke dosen (seperti siswa memilih dosen saat daftar). */
    protected function linkStudents(\App\Models\User $teacher, \App\Models\User ...$students): void
    {
        foreach ($students as $s) {
            $s->teachers()->syncWithoutDetaching([$teacher->id]);
        }
    }
}
