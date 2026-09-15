@extends('layouts.app')
@section('title', 'Akses Ditolak')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h1 class="display-1">🚫</h1>
                <h4 class="text-danger">Kuis Ini Sedang Dikerjakan dari Perangkat/Browser Lain</h4>
                <p class="text-muted mt-3">
                    Sistem mendeteksi kuis <strong>"{{ $attempt->quiz->title }}"</strong> sedang aktif
                    dikerjakan dari perangkat atau browser lain dengan akun ini.
                </p>
                <p class="text-muted">
                    Kalau ini memang perangkat yang seharusnya Anda pakai (misalnya perangkat
                    sebelumnya bermasalah/mati), hubungi dosen pengampu untuk me-reset akses
                    perangkat pada kuis ini.
                </p>
                <a href="{{ route('mahasiswa.quizzes.index') }}" class="btn btn-outline-secondary mt-2">Kembali ke Daftar Kuis</a>
            </div>
        </div>
    </div>
</div>
@endsection
