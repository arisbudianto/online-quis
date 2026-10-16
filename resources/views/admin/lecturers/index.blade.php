@extends('layouts.app')
@section('title', 'Kelola Dosen')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kelola Dosen / Guru</h3>
    <a href="{{ route('admin.lecturers.create') }}" class="btn btn-primary">+ Buat Akun Dosen</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4 {{ $pending->isNotEmpty() ? 'border-warning' : '' }}">
    <div class="card-header">Menunggu persetujuan ({{ $pending->count() }})</div>
    @if ($pending->isEmpty())
        <div class="card-body text-muted">Tidak ada pendaftaran dosen/guru baru.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Nama</th><th>Email</th><th>Instansi</th><th>Daftar</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($pending as $p)
                        <tr>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->email }} @if ($p->google_id)<span class="badge bg-light text-dark border">Google</span>@endif</td>
                            <td>{{ $p->institution ?? '-' }}</td>
                            <td class="small">{{ $p->created_at->format('d-m-Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('admin.lecturers.approve', $p) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Setujui</button>
                                </form>
                                <form method="POST" action="{{ route('admin.lecturers.reject', $p) }}" class="d-inline"
                                      onsubmit="return confirm('Tolak dan hapus pendaftaran {{ addslashes($p->name) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Tolak</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Instansi</th>
            <th>Siswa</th>
            <th>Mata Kuliah</th>
            <th>Kuis Dibuat</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lecturers as $l)
            <tr>
                <td>{{ $l->name }}</td>
                <td>{{ $l->email }}</td>
                <td>{{ $l->institution ?? '-' }}</td>
                <td>{{ $l->students_count }}</td>
                <td>{{ $l->courses_count }}</td>
                <td>{{ $l->quizzes_count }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Belum ada dosen terdaftar.</td></tr>
        @endforelse
    </tbody>
</table>
</div>

{{ $lecturers->links() }}
@endsection
