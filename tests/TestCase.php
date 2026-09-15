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
}
