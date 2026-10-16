<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            // Tahun awal tahun ajaran: 2026 berarti "2026/2027".
            $table->unsignedSmallInteger('year_start');
            // String, bukan enum, supaya nilai baru (misal "antara") cukup
            // ditambah di AcademicPeriod::SEMESTERS tanpa migration.
            $table->string('semester', 20);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['year_start', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
