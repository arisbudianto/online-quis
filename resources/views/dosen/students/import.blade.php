@extends('layouts.app')
@section('title', 'Import Mahasiswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Import Mahasiswa Massal</h3>
    <a href="{{ route('dosen.students.index') }}" class="btn btn-outline-secondary btn-sm">Lihat Daftar Mahasiswa</a>
</div>

<div class="card">
    <div class="card-body">
        <a href="{{ asset('templates/template-mahasiswa.xlsx') }}" class="btn btn-outline-success btn-sm mb-3" download>
            ⬇ Download Template Excel (.xlsx)
        </a>

        <form method="POST" action="{{ route('dosen.students.import') }}" enctype="multipart/form-data">
            @csrf

            <input type="file" name="file" accept=".xlsx" class="form-control mb-3" required>

            <div class="form-check mb-3">
                <input type="checkbox" name="send_email" value="1" class="form-check-input" id="send_email" checked>
                <label class="form-check-label" for="send_email">
                    Kirim email kredensial (email + password) ke tiap mahasiswa yang berhasil didaftarkan
                </label>
            </div>

            <button class="btn btn-primary">Import Mahasiswa</button>
        </form>

        <hr>
        <small class="text-muted">
            Kolom wajib: <strong>Nama, NIM, Kelas (A/B/C), Email</strong>.
            NIM dan Email yang sudah terdaftar sebelumnya otomatis dilewati (tidak dibuat ulang).
            <br>
            <strong>Tips:</strong> di Excel, format kolom NIM sebagai <strong>Text</strong> sebelum diisi
            data, supaya angka 0 di depan NIM (kalau ada) tidak hilang otomatis oleh Excel.
        </small>
    </div>
</div>
@endsection
