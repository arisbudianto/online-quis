@extends('layouts.app')
@section('title', 'Kelola Dosen')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kelola Dosen</h3>
    <a href="{{ route('admin.lecturers.create') }}" class="btn btn-primary">+ Buat Akun Dosen</a>
</div>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Mata Kuliah</th>
            <th>Kuis Dibuat</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lecturers as $l)
            <tr>
                <td>{{ $l->name }}</td>
                <td>{{ $l->email }}</td>
                <td>{{ $l->courses_count }}</td>
                <td>{{ $l->quizzes_count }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">Belum ada dosen terdaftar.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $lecturers->links() }}
@endsection
