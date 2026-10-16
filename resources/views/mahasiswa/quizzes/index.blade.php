@extends('layouts.app')
@section('title', 'Daftar Kuis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kuis Tersedia</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('mahasiswa.quizzes.history') }}" class="btn btn-outline-success">🗂 Riwayat Nilai Saya</a>
        <a href="{{ route('mahasiswa.quizzes.join') }}" class="btn btn-outline-primary">🔑 Masukkan Kode Kuis</a>
    </div>
</div>

<div class="row">
    @forelse ($quizzes as $quiz)
        @php $used = $attemptCounts[$quiz->id] ?? 0; @endphp
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5>{{ $quiz->title }}</h5>
                    <p class="text-muted">{{ $quiz->description }}</p>
                    <p class="mb-1">Durasi: {{ $quiz->duration_minutes }} menit</p>
                    <p class="mb-1">Dikerjakan satu kali</p>

                    @if (isset($ongoingQuizIds[$quiz->id]))
                        <form method="POST" action="{{ route('mahasiswa.quizzes.start', $quiz) }}">
                            @csrf
                            <button class="btn btn-warning btn-sm">Lanjutkan Kuis</button>
                        </form>
                    @elseif (!$quiz->isOpenNow())
                        <span class="badge bg-secondary">Belum/Tidak dibuka</span>
                    @elseif ($used >= \App\Models\Quiz::MAX_ATTEMPTS)
                        <span class="badge bg-success">Sudah dikerjakan</span>
                    @else
                        <form method="POST" action="{{ route('mahasiswa.quizzes.start', $quiz) }}">
                            @csrf
                            <button class="btn btn-primary btn-sm">Mulai Kuis</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted">Belum ada kuis publik yang tersedia. Punya kode kuis dari dosen? Klik "Masukkan Kode Kuis" di atas.</p>
    @endforelse
</div>
@endsection
