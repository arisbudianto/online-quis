@extends('layouts.app')
@section('title', 'Buat Kuis')

@section('content')
<h3>Buat Kuis Baru</h3>

<form method="POST" action="{{ route('dosen.quizzes.store') }}" class="bg-white p-4 rounded shadow-sm">
    @csrf

    <div class="mb-3">
        <label class="form-label">Mata Kuliah</label>
        <select name="course_id" class="form-control">
            <option value="">— Umum / Tidak dikaitkan —</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>{{ $course->name }}</option>
            @endforeach
        </select>
        @if ($courses->isEmpty())
            <small class="text-muted">Belum ada mata kuliah. <a href="{{ route('dosen.courses.index') }}">Tambah mata kuliah dulu</a> (opsional).</small>
        @endif
    </div>

    <div class="mb-3">
        <label class="form-label">Periode (Tahun Ajaran / Semester)</label>
        <select name="academic_period_id" class="form-control">
            <option value="">— Tanpa periode —</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected((string) old('academic_period_id', $defaultPeriodId) === (string) $period->id)>
                    {{ $period->label }}{{ $period->is_active ? ' (aktif)' : '' }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Dipakai untuk rekap nilai per semester. Kuis tanpa periode tidak masuk rekap.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Judul Kuis</label>
        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control">{{ old('description') }}</textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Durasi (menit)</label>
            <input type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', 60) }}" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Waktu Buka</label>
            <input type="datetime-local" name="start_time" class="form-control" value="{{ old('start_time') }}">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Waktu Tutup</label>
            <input type="datetime-local" name="end_time" class="form-control" value="{{ old('end_time') }}">
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Maks. Percobaan</label>
            <input type="number" name="max_attempts" class="form-control" value="{{ old('max_attempts', 1) }}" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Jumlah Soal Ditampilkan (kosongkan = semua)</label>
            <input type="number" name="questions_to_show" class="form-control" value="{{ old('questions_to_show') }}">
        </div>
    </div>

    <div class="form-check mb-2">
        <input type="checkbox" name="shuffle_questions" value="1" class="form-check-input" checked>
        <label class="form-check-label">Acak urutan soal per mahasiswa</label>
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" name="shuffle_options" value="1" class="form-check-input" checked>
        <label class="form-check-label">Acak urutan pilihan jawaban per mahasiswa</label>
    </div>

    <button class="btn btn-primary">Simpan & Lanjut Unggah Soal</button>
    <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-link">Batal</a>
</form>
@endsection
