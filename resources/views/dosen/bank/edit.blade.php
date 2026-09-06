@extends('layouts.app')
@section('title', 'Edit Soal Bank')

@section('content')
<h3>Edit Soal</h3>

<form method="POST" action="{{ route('dosen.bank.update', $bankQuestion) }}" class="bg-white p-4 rounded shadow-sm">
    @csrf @method('PUT')

    <div class="mb-3">
        <label class="form-label">Pertanyaan</label>
        <textarea name="question_text" class="form-control" rows="3" required>{{ old('question_text', $bankQuestion->question_text) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Tag/Topik (opsional)</label>
        <input type="text" name="tag" class="form-control" value="{{ old('tag', $bankQuestion->tag) }}" placeholder="Contoh: Bab 3 - Teori Belajar">
    </div>

    <label class="form-label">Opsi Jawaban (pilih radio pada opsi yang benar)</label>
    @foreach ($bankQuestion->options as $i => $opt)
        <div class="input-group mb-2">
            <div class="input-group-text">
                <input type="radio" name="correct_index" value="{{ $i }}" {{ $opt->is_correct ? 'checked' : '' }} required>
            </div>
            <input type="text" name="options[{{ $i }}][text]" class="form-control" value="{{ old("options.$i.text", $opt->option_text) }}" required>
        </div>
    @endforeach

    <button class="btn btn-primary mt-2">Simpan Perubahan</button>
    <a href="{{ route('dosen.bank.index', $bankQuestion->course_id) }}" class="btn btn-link">Batal</a>
</form>
@endsection
