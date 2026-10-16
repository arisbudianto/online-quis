@extends('layouts.app')
@section('title', 'Kelas Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kelas Saya</h3>
    <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Saya</a>
</div>

<p class="text-muted">
    Siswa memilih salah satu kelas ini saat mendaftar dan memilih Anda sebagai dosen/guru.
    Kelas juga dipakai untuk filter Daftar Mahasiswa, Peserta, dan Rekap Nilai.
</p>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row">
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header">Tambah kelas</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dosen.classes.store') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="name" class="form-control" maxlength="30" required placeholder="contoh A, B, X IPA 1" value="{{ old('name') }}">
                    <button class="btn btn-primary">Tambah</button>
                </form>
                @error('name')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <table class="table table-bordered bg-white align-middle">
            <thead class="table-light"><tr><th>Kelas</th><th class="text-center">Siswa Anda</th><th></th></tr></thead>
            <tbody>
                @forelse ($classes as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->name }}</td>
                        <td class="text-center">{{ $counts[$c->name] ?? 0 }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('dosen.classes.destroy', $c) }}"
                                  onsubmit="return confirm('Hapus kelas {{ addslashes($c->name) }} dari daftar? Siswa yang sudah di kelas ini tidak berubah.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Belum ada kelas. Tambahkan minimal satu supaya siswa bisa memilih kelas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
