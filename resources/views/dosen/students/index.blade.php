@extends('layouts.app')
@section('title', 'Daftar Mahasiswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Daftar Mahasiswa</h3>
    <a href="{{ route('dosen.students.import.form') }}" class="btn btn-primary btn-sm">+ Import Mahasiswa</a>
</div>

<div class="btn-group mb-3">
    <a href="{{ route('dosen.students.index') }}" class="btn btn-sm {{ !$kelas ? 'btn-dark' : 'btn-outline-dark' }}">Semua</a>
    @foreach (['A', 'B', 'C'] as $k)
        <a href="{{ route('dosen.students.index', ['kelas' => $k]) }}" class="btn btn-sm {{ $kelas === $k ? 'btn-dark' : 'btn-outline-dark' }}">Kelas {{ $k }}</a>
    @endforeach
</div>

<table class="table table-bordered bg-white">
    <thead>
        <tr>
            <th>Nama</th>
            <th>NIM</th>
            <th>Kelas</th>
            <th>Email</th>
            <th>Terdaftar</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $s)
            <tr>
                <td>{{ $s->name }}</td>
                <td>{{ $s->nim }}</td>
                <td>{{ $s->kelas ?? '-' }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ $s->created_at->format('d-m-Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Belum ada mahasiswa terdaftar.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $students->links() }}
@endsection
