@extends('layouts.app')
@section('title', 'Riwayat Nilai Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Riwayat Nilai Saya</h3>
    <a href="{{ route('mahasiswa.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Tersedia</a>
</div>

<table class="table table-bordered bg-white">
    <thead>
        <tr>
            <th>Kuis</th>
            <th>Mata Kuliah</th>
            <th>Skor</th>
            <th>Waktu Submit</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($attempts as $a)
            <tr>
                <td>{{ $a->quiz->title }}</td>
                <td>{{ $a->quiz->course->name ?? '-' }}</td>
                <td>
                    @if ($a->needs_grading)
                        <span class="badge bg-warning text-dark">Menunggu dinilai dosen</span>
                    @else
                        <span class="badge {{ $a->score >= 75 ? 'bg-success' : ($a->score >= 50 ? 'bg-warning text-dark' : 'bg-danger') }}">
                            {{ $a->score }}
                        </span>
                    @endif
                </td>
                <td>{{ optional($a->submitted_at)->format('d-m-Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">Anda belum pernah menyelesaikan kuis apa pun.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $attempts->links() }}
@endsection
