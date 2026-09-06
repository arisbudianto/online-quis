@extends('layouts.app')
@section('title', 'Mata Kuliah')

@section('content')
<h3>Mata Kuliah yang Diampu</h3>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header">Tambah Mata Kuliah</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dosen.courses.store') }}">
                    @csrf
                    <input type="text" name="name" class="form-control mb-2" placeholder="Nama mata kuliah" value="{{ old('name') }}" required>
                    <button class="btn btn-primary btn-sm">Tambah</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <table class="table table-bordered bg-white align-middle">
            <thead>
                <tr>
                    <th>Mata Kuliah</th>
                    <th>Jumlah Kuis</th>
                    <th>Soal di Bank</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $course)
                    <tr>
                        <td>{{ $course->name }}</td>
                        <td>{{ $course->quizzes_count }}</td>
                        <td>{{ $course->bank_questions_count }}</td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('dosen.bank.index', $course) }}" class="btn btn-sm btn-outline-primary">📚 Bank Soal</a>
                            <form method="POST" action="{{ route('dosen.courses.destroy', $course) }}" onsubmit="return confirm('Hapus mata kuliah ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Belum ada mata kuliah.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
