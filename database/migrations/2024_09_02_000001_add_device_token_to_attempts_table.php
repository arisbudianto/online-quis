<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // Token acak panjang, disimpan juga sebagai cookie di browser
            // mahasiswa. Independen dari siklus hidup session Laravel
            // (tidak ikut berubah kalau session di-garbage-collect / di-
            // regenerate karena alasan teknis biasa), jadi jauh lebih
            // stabil dipakai sebagai penanda "device/browser yang sama"
            // dibanding session()->getId().
            $table->string('device_token', 64)->nullable()->after('browser_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('device_token');
        });
    }
};
