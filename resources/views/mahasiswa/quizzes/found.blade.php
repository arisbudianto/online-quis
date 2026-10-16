@extends('layouts.app')
@section('title', 'Kuis Ditemukan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h4>{{ $quiz->title }}</h4>
                @unless ($quiz->is_public)
                    <span class="badge bg-dark mb-2">🔒 Kuis Privat — hanya bisa diakses lewat kode ini</span>
                @endunless
                <p class="text-muted">{{ $quiz->description }}</p>
                <p class="mb-1">Durasi: {{ $quiz->duration_minutes }} menit</p>
                <p class="mb-1">Percobaan: {{ $used }} / {{ $quiz->max_attempts }}</p>

                @if (!$quiz->isOpenNow())
                    <div class="alert alert-warning mt-3 mb-0">
                        Kuis ini belum dibuka, sudah ditutup, atau belum diterbitkan oleh dosen.
                    </div>
                @elseif ($used >= $quiz->max_attempts)
                    <div class="alert alert-danger mt-3 mb-0">
                        Anda sudah mencapai batas maksimal percobaan untuk kuis ini.
                    </div>
                @else
                    <form method="POST" action="{{ route('mahasiswa.quizzes.start', $quiz) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="code" value="{{ $quiz->code }}">
                        <button class="btn btn-primary">Mulai Kuis</button>
                    </form>
                @endif

                <hr>
                <a href="{{ route('mahasiswa.quizzes.join') }}" class="text-muted">← Masukkan kode lain</a>
            </div>
        </div>
    </div>
</div>
@endsection
