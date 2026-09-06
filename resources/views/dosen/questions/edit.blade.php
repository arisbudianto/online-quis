@extends('layouts.app')
@section('title', 'Edit Soal')

@section('content')
<h3>Edit Soal</h3>
<p class="text-muted">{{ $question->quiz->title }}</p>

<form method="POST" action="{{ route('dosen.questions.update', $question) }}" class="bg-white p-4 rounded shadow-sm">
    @csrf @method('PUT')

    <div class="mb-3">
        <label class="form-label">Pertanyaan</label>
        <textarea name="question_text" class="form-control" rows="3" required>{{ old('question_text', $question->question_text) }}</textarea>
    </div>

    @if ($question->image_path)
        <div class="mb-3">
            <label class="form-label d-block">Gambar Saat Ini</label>
            <img src="{{ asset($question->image_path) }}" alt="Gambar soal" style="max-width: 250px; max-height: 180px;" class="border rounded">
            <small class="text-muted d-block">Untuk mengganti gambar, hapus soal ini dan buat ulang lewat "Tambah Soal Manual".</small>
        </div>
    @endif

    <label class="form-label">Opsi Jawaban (pilih radio pada opsi yang benar)</label>
    @foreach ($question->options as $i => $opt)
        <div class="input-group mb-2">
            <div class="input-group-text">
                <input type="radio" name="correct_index" value="{{ $i }}" {{ $opt->is_correct ? 'checked' : '' }} required>
            </div>
            <input type="text" name="options[{{ $i }}][text]" class="form-control" value="{{ old("options.$i.text", $opt->option_text) }}" required>
        </div>
    @endforeach

    <button class="btn btn-primary mt-2">Simpan Perubahan</button>
    <a href="{{ route('dosen.quizzes.edit', $question->quiz) }}" class="btn btn-link">Batal</a>
</form>

<form method="POST" action="{{ route('dosen.questions.destroy', $question) }}" class="mt-3" onsubmit="return confirm('Hapus soal ini dari kuis? Tindakan ini tidak bisa dibatalkan.')">
    @csrf @method('DELETE')
    <button class="btn btn-outline-danger btn-sm">🗑 Hapus Soal Ini</button>
</form>
@endsection
