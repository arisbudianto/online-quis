@extends('layouts.app')
@section('title', 'Nilai Esai')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Nilai Esai: {{ $quiz->title }}</h3>
    <a href="{{ route('dosen.quizzes.scores', $quiz) }}" class="btn btn-outline-secondary btn-sm">← Nilai</a>
</div>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>NIM</th>
            <th>Nama</th>
            <th>Status</th>
            <th>Skor Akhir</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($attempts as $a)
            <tr>
                <td>{{ $a->user->nim }}</td>
                <td>{{ $a->user->name }}</td>
                <td>
                    @if ($a->needs_grading)
                        <span class="badge bg-warning text-dark">Belum Dinilai</span>
                    @else
                        <span class="badge bg-success">Sudah Final</span>
                    @endif
                </td>
                <td>{{ $a->needs_grading ? '-' : $a->score }}</td>
                <td>
                    <a href="{{ route('dosen.grading.edit', $a) }}" class="btn btn-sm btn-outline-primary">
                        {{ $a->needs_grading ? 'Nilai Sekarang' : 'Lihat/Edit Nilai' }}
                    </a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Belum ada mahasiswa yang submit.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
