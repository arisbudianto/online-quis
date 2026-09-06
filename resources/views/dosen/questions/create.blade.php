@extends('layouts.app')
@section('title', 'Tambah Soal')

@section('content')
<h3>Tambah Soal ke: {{ $quiz->title }}</h3>

<form method="POST" action="{{ route('dosen.quizzes.questions.store', $quiz) }}" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
    @csrf

    <div class="mb-3">
        <label class="form-label">Tipe Soal</label>
        <select name="type" id="type-select" class="form-control" required>
            <option value="pilihan_ganda" {{ old('type') === 'pilihan_ganda' ? 'selected' : '' }}>Pilihan Ganda</option>
            <option value="isian_singkat" {{ old('type') === 'isian_singkat' ? 'selected' : '' }}>Isian Singkat (auto-grading)</option>
            <option value="esai" {{ old('type') === 'esai' ? 'selected' : '' }}>Esai (dinilai manual)</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Pertanyaan</label>
        <textarea name="question_text" class="form-control" rows="3" required>{{ old('question_text') }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Gambar (opsional)</label>
        <input type="file" name="image" accept="image/*" class="form-control">
        <small class="text-muted">Ditampilkan di atas teks soal saat mahasiswa mengerjakan.</small>
    </div>

    <div id="mc-fields">
        <label class="form-label">Opsi Jawaban (pilih radio pada opsi yang benar)</label>
        @for ($i = 0; $i < 4; $i++)
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input type="radio" name="correct_index" value="{{ $i }}" {{ old('correct_index') == $i ? 'checked' : '' }}>
                </div>
                <input type="text" name="options[{{ $i }}][text]" class="form-control" value="{{ old("options.$i.text") }}" placeholder="Opsi {{ chr(65 + $i) }}">
            </div>
        @endfor
    </div>

    <div id="short-answer-fields" class="mb-3" style="display: none;">
        <label class="form-label">Jawaban Benar (untuk dicocokkan otomatis)</label>
        <input type="text" name="correct_text_answer" class="form-control" value="{{ old('correct_text_answer') }}" placeholder="Contoh: 96">
        <small class="text-muted">Pencocokan tidak peka huruf besar/kecil dan spasi berlebih.</small>
    </div>

    <div id="essay-note" class="alert alert-info" style="display: none;">
        Soal esai tidak punya jawaban benar otomatis — Anda akan menilainya manual
        lewat halaman "Nilai Esai" setelah mahasiswa submit.
    </div>

    <button class="btn btn-primary mt-2">Simpan Soal</button>
    <a href="{{ route('dosen.quizzes.edit', $quiz) }}" class="btn btn-link">Batal</a>
</form>
@endsection

@section('scripts')
<script>
    const typeSelect = document.getElementById('type-select');
    const mcFields = document.getElementById('mc-fields');
    const shortFields = document.getElementById('short-answer-fields');
    const essayNote = document.getElementById('essay-note');

    function toggleFields() {
        const type = typeSelect.value;
        mcFields.style.display = type === 'pilihan_ganda' ? 'block' : 'none';
        shortFields.style.display = type === 'isian_singkat' ? 'block' : 'none';
        essayNote.style.display = type === 'esai' ? 'block' : 'none';
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
</script>
@endsection
