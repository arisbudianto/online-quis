@extends('layouts.app')
@section('title', 'Dashboard Admin')

@section('content')
<h3>Dashboard Admin</h3>
<p class="text-muted">Ringkasan sistem &amp; menu administratif.</p>

@if ($stats['dosen_pending'] > 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>{{ $stats['dosen_pending'] }} pendaftaran dosen/guru menunggu persetujuan.</span>
        <a href="{{ route('admin.lecturers.index') }}" class="btn btn-sm btn-warning">Periksa</a>
    </div>
@endif

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6 fw-bold">{{ $stats['total_dosen'] }}</div>
                <div class="text-muted">Dosen</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6 fw-bold">{{ $stats['total_mahasiswa'] }}</div>
                <div class="text-muted">Mahasiswa</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6 fw-bold">{{ $stats['total_kuis'] }}</div>
                <div class="text-muted">Kuis</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-6 fw-bold">{{ $stats['total_attempt'] }}</div>
                <div class="text-muted">Attempt Selesai</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100 {{ $stats['dosen_pending'] > 0 ? 'border-warning' : '' }}">
            <div class="card-body">
                <h5>
                    Verifikasi Dosen / Guru
                    @if ($stats['dosen_pending'] > 0)
                        <span class="badge bg-warning text-dark">{{ $stats['dosen_pending'] }} baru</span>
                    @endif
                </h5>
                <p class="text-muted small">Setujui atau tolak dosen/guru yang mendaftar sendiri. Sebelum disetujui mereka belum bisa membuat kuis.</p>
                <a href="{{ route('admin.lecturers.index') }}#menunggu" class="btn btn-warning btn-sm">Periksa pendaftar →</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Kelola Dosen</h5>
                <p class="text-muted small">Lihat semua dosen, buat akun dosen baru.</p>
                <a href="{{ route('admin.lecturers.index') }}" class="btn btn-primary btn-sm">Buka →</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Semua Mahasiswa</h5>
                <p class="text-muted small">Lihat mahasiswa lintas dosen/kelas.</p>
                <a href="{{ route('admin.students.index') }}" class="btn btn-primary btn-sm">Buka →</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Backup Database</h5>
                <p class="text-muted small">Lihat riwayat backup, unduh, atau backup manual.</p>
                <a href="{{ route('admin.backups.index') }}" class="btn btn-primary btn-sm">Buka →</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Periode Akademik</h5>
                <p class="text-muted small">Tahun ajaran dan semester. Kuis baru memakai periode aktif.</p>
                <a href="{{ route('admin.periods.index') }}" class="btn btn-primary btn-sm">Buka →</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>Konversi Nilai Huruf</h5>
                <p class="text-muted small">Batas nilai angka dan bobot tiap huruf (A sampai E) untuk rekap semester.</p>
                <a href="{{ route('admin.gradeScales.index') }}" class="btn btn-primary btn-sm">Buka →</a>
            </div>
        </div>
    </div>
</div>
@endsection
