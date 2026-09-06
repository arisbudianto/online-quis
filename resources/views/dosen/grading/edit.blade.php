@extends('layouts.app')
@section('title', 'Nilai Esai')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3>Nilai Esai: {{ $attempt->user->name }}</h3>
        <p class="text-muted mb-0">{{ $attempt->user->nim }} &middot; {{ $attempt->quiz->title }}</p>
    </div>
    <a href="{{ route('dosen.grading.index', $attempt->quiz) }}" class="btn btn-outline-secondary btn-sm">← Daftar Mahasiswa</a>
</div>

<form method="POST" action="{{ route('dosen.grading.update', $attempt) }}">
    @csrf @method('PUT')

    @forelse ($attempt->answers as $answer)
        <div class="card mb-3">
            <div class="card-body">
                <p class="fw-bold">{{ $answer->question->question_text }}</p>

                @if ($answer->question->image_path)
                    <img src="{{ asset($answer->question->image_path) }}" alt="Gambar soal" style="max-width:100%; max-height:250px;" class="mb-2 border rounded d-block">
                @endif

                <div class="bg-light p-3 rounded mb-3">
                    <small class="text-muted d-block mb-1">Jawaban mahasiswa:</small>
                    {{ $answer->answer_text ?: '(tidak dijawab)' }}
                </div>

                <label class="form-label">Poin (0-100)</label>
                <input
                    type="number"
                    name="points[{{ $answer->id }}]"
                    class="form-control"
                    style="max-width: 150px"
                    min="0" max="100" step="0.5"
                    value="{{ old("points.{$answer->id}", $answer->points) }}"
                >
            </div>
        </div>
    @empty
        <p class="text-muted">Tidak ada jawaban esai di attempt ini.</p>
    @endforelse

    <button class="btn btn-primary">Simpan Nilai</button>
</form>
@endsection
