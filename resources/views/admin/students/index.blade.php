@extends('layouts.app')
@section('title', 'Semua Mahasiswa')

@section('content')
<h3>Semua Mahasiswa</h3>

<div class="btn-group mb-3">
    <a href="{{ route('admin.students.index') }}" class="btn btn-sm {{ !$kelas ? 'btn-dark' : 'btn-outline-dark' }}">Semua</a>
    @foreach (['A', 'B', 'C'] as $k)
        <a href="{{ route('admin.students.index', ['kelas' => $k]) }}" class="btn btn-sm {{ $kelas === $k ? 'btn-dark' : 'btn-outline-dark' }}">Kelas {{ $k }}</a>
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
