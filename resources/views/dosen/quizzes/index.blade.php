@extends('layouts.app')
@section('title', 'Daftar Kuis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Kuis Saya</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('dosen.recap.index') }}" class="btn btn-outline-success">📈 Rekap Nilai</a>
        <a href="{{ route('dosen.courses.index') }}" class="btn btn-outline-secondary">📚 Mata Kuliah</a>
        <a href="{{ route('dosen.students.index') }}" class="btn btn-outline-secondary">🎓 Mahasiswa</a>
        <a href="{{ route('dosen.quizzes.create') }}" class="btn btn-primary">+ Buat Kuis</a>
    </div>
</div>

<form method="GET" action="{{ route('dosen.quizzes.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-sm-4">
        <label class="form-label small mb-1">Periode</label>
        <select name="periode" class="form-select form-select-sm">
            <option value="">Semua periode</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected($periodFilter === (string) $period->id)>{{ $period->label }}</option>
            @endforeach
            <option value="none" @selected($periodFilter === 'none')>Belum diisi</option>
        </select>
    </div>
    <div class="col-sm-4">
        <label class="form-label small mb-1">Mata Kuliah</label>
        <select name="mk" class="form-select form-select-sm">
            <option value="">Semua mata kuliah</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected($courseFilter === (string) $course->id)>{{ $course->name }}</option>
            @endforeach
            <option value="none" @selected($courseFilter === 'none')>Tidak dikaitkan</option>
        </select>
    </div>
    <div class="col-sm-4 d-flex gap-2">
        <button class="btn btn-sm btn-secondary">Terapkan</button>
        @if ($periodFilter !== '' || $courseFilter !== '')
            <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        @endif
    </div>
</form>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>Judul</th>
            <th>Periode</th>
            <th>Mata Kuliah</th>
            <th>Kode</th>
            <th>Jumlah Soal</th>
            <th>Status Aktif</th>
            <th>Sudah Mengerjakan</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($quizzes as $quiz)
            <tr>
                <td>{{ $quiz->title }}</td>
                <td>{{ $quiz->academicPeriod?->label ?? '—' }}</td>
                <td>{{ $quiz->course->name ?? '—' }}</td>
                <td><code>{{ $quiz->code ?? '-' }}</code></td>
                <td>{{ $quiz->questions_count }}</td>
                <td>
                    @if (!$quiz->is_published)
                        <span class="badge bg-secondary">Draft</span>
                    @elseif ($quiz->isOpenNow())
                        <span class="badge bg-success">🟢 Aktif Sekarang</span>
                    @elseif ($quiz->start_time && now()->lt($quiz->start_time))
                        <span class="badge bg-warning text-dark">⏳ Belum Dibuka</span>
                    @else
                        <span class="badge bg-danger">🔴 Sudah Tutup</span>
                    @endif
                    <span class="badge {{ $quiz->is_public ? 'bg-info text-dark' : 'bg-dark' }}">
                        {{ $quiz->is_public ? 'Publik' : '🔒 Privat (kode saja)' }}
                    </span>
                </td>
                <td>
                    <strong>{{ $quiz->submitted_count }}</strong> mahasiswa
                </td>
                <td class="d-flex gap-2">
                    <a href="{{ route('dosen.quizzes.edit', $quiz) }}" class="btn btn-sm btn-outline-primary">Kelola</a>
                    <a href="{{ route('dosen.quizzes.scores', $quiz) }}" class="btn btn-sm btn-outline-success">Nilai</a>
                    <a href="{{ route('dosen.quizzes.analytics', $quiz) }}" class="btn btn-sm btn-outline-info">📊 Analitik</a>
                    <form method="POST" action="{{ route('dosen.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Hapus kuis ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted">Belum ada kuis.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $quizzes->links() }}
@endsection
