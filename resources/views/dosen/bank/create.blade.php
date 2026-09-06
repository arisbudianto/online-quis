@extends('layouts.app')
@section('title', 'Tambah Soal ke Bank')

@section('content')
<h3>Tambah Soal ke Bank: {{ $course->name }}</h3>

<form method="POST" action="{{ route('dosen.bank.storeManual', $course) }}" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
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
        <label class="form-label">Tag/Topik (opsional)</label>
        <input type="text" name="tag" class="form-control" value="{{ old('tag') }}" placeholder="Contoh: Bab 3 - Teori Belajar">
    </div>

    <div class="mb-3">
        <label class="form-label">Gambar (opsional)</label>
        <input type="file" name="image" accept="image/*" class="form-control">
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
        <input type="text" name="correct_text_answer" class="form-control" value="{{ old('correct_text_answer') }}">
    </div>

    <div id="essay-note" class="alert alert-info" style="display: none;">
        Soal esai tidak punya jawaban benar otomatis — dinilai manual dosen setelah dipakai di kuis.
    </div>

    <button class="btn btn-primary mt-2">Simpan ke Bank</button>
    <a href="{{ route('dosen.bank.index', $course) }}" class="btn btn-link">Batal</a>
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
