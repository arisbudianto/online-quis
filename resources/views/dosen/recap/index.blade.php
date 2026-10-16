@extends('layouts.app')
@section('title', 'Rekap Nilai Semester')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Rekap Nilai Semester</h3>
    <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Saya</a>
</div>

<form method="GET" action="{{ route('dosen.recap.index') }}" class="row g-2 align-items-end mb-3">
    <div class="col-sm-3">
        <label class="form-label small mb-1">Periode</label>
        <select name="periode" class="form-select form-select-sm">
            @forelse ($periods as $p)
                <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->label }}{{ $p->is_active ? ' (aktif)' : '' }}</option>
            @empty
                <option value="">Belum ada periode, minta admin menambahkan</option>
            @endforelse
        </select>
    </div>
    <div class="col-sm-3">
        <label class="form-label small mb-1">Mata Kuliah</label>
        <select name="mk" class="form-select form-select-sm">
            <option value="">Pilih mata kuliah</option>
            @foreach ($courses as $c)
                <option value="{{ $c->id }}" @selected($course?->id === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <label class="form-label small mb-1">Kelas</label>
        <select name="kelas" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach (($recap['kelasOptions'] ?? []) as $k)
                <option value="{{ $k }}" @selected($kelas === $k)>{{ $k }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <label class="form-label small mb-1">Angkatan</label>
        <select name="angkatan" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach (($recap['angkatanOptions'] ?? \App\Support\Angkatan::options()) as $a => $aLabel)
                <option value="{{ $a }}" @selected($angkatan === (string) $a)>{{ $aLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <button class="btn btn-sm btn-primary w-100">Tampilkan</button>
    </div>
</form>

@if (!$period || !$course)
    <div class="alert alert-info">Pilih periode dan mata kuliah untuk melihat rekap.</div>
@elseif ($recap['quizzes']->isEmpty())
    <div class="alert alert-warning">
        Belum ada kuis {{ $course->name }} di periode {{ $period->label }}.
        Pastikan kuis sudah dikaitkan ke mata kuliah dan periode ini di Pengaturan Kuis.
    </div>
@elseif ($recap['enrolledCount'] === 0)
    <div class="alert alert-warning">
        Belum ada peserta resmi {{ $course->name }} di periode {{ $period->label }}.
        <a href="{{ route('dosen.enrollments.index', [$course, 'periode' => $period->id]) }}">Tambahkan peserta</a>
        supaya rekap bisa disusun.
        @if ($recap['unenrolled']->isNotEmpty())
            Sudah ada {{ $recap['unenrolled']->count() }} mahasiswa yang mengerjakan kuis mata kuliah ini.
        @endif
    </div>
@else
    @if ($recap['unenrolled']->isNotEmpty())
        <div class="alert alert-warning small">
            {{ $recap['unenrolled']->count() }} mahasiswa mengerjakan kuis mata kuliah ini tetapi tidak terdaftar sebagai peserta,
            sehingga tidak masuk rekap:
            {{ $recap['unenrolled']->take(10)->map(fn ($u) => $u->nim . ' ' . $u->name)->implode(', ') }}{{ $recap['unenrolled']->count() > 10 ? ', ...' : '' }}.
            <a href="{{ route('dosen.enrollments.index', [$course, 'periode' => $period->id]) }}">Kelola peserta</a>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small">
            {{ $course->name }} · {{ $period->label }} · {{ $recap['quizzes']->count() }} kuis · {{ $recap['rows']->count() }} peserta
            · <a href="{{ route('dosen.enrollments.index', [$course, 'periode' => $period->id]) }}">kelola peserta</a>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dosen.recap.analytics', array_filter(['periode' => $period->id, 'mk' => $course->id, 'kelas' => $kelas, 'angkatan' => $angkatan])) }}"
               class="btn btn-sm btn-outline-primary">📊 Analitik</a>
            <a href="{{ route('dosen.recap.export', array_filter(['periode' => $period->id, 'mk' => $course->id, 'kelas' => $kelas, 'angkatan' => $angkatan])) }}"
               class="btn btn-sm btn-success">Export Excel</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm bg-white align-middle">
            <thead class="table-light">
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Angkatan</th>
                    @foreach ($recap['quizzes'] as $quiz)
                        <th class="text-center" title="{{ $quiz->title }}">{{ \Illuminate\Support\Str::limit($quiz->title, 24) }}</th>
                    @endforeach
                    <th class="text-center">Nilai Akhir</th>
                    <th class="text-center">Huruf</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recap['rows'] as $row)
                    <tr>
                        <td>{{ $row['student']->nim }}</td>
                        <td>{{ $row['student']->name }}</td>
                        <td>{{ $row['student']->kelas }}</td>
                        <td>{{ $row['student']->angkatan ?? 'Lainnya' }}</td>
                        @foreach ($recap['quizzes'] as $quiz)
                            @php($cell = $row['cells'][$quiz->id])
                            <td class="text-center">
                                @if ($cell['status'] === 'final')
                                    {{ rtrim(rtrim(number_format($cell['score'], 2, ',', ''), '0'), ',') }}
                                @elseif ($cell['status'] === 'pending')
                                    <span class="badge bg-warning text-dark" title="Menunggu nilai esai">esai</span>
                                @else
                                    <span class="text-muted" title="Tidak mengerjakan, dihitung 0">–</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-center fw-bold">
                            @if ($row['complete'])
                                {{ rtrim(rtrim(number_format($row['final'], 2, ',', ''), '0'), ',') }}
                            @else
                                <span class="badge bg-secondary" title="Ada esai yang belum dinilai">belum final</span>
                            @endif
                        </td>
                        <td class="text-center fw-bold" title="{{ $row['weight'] !== null ? 'Bobot ' . number_format($row['weight'], 1, ',', '') : '' }}">
                            {{ $row['letter'] ?? '' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 6 + $recap['quizzes']->count() }}" class="text-center text-muted">Tidak ada peserta dengan filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="small text-muted">
        Setiap kuis dikerjakan satu kali; untuk data lama yang terlanjur lebih dari sekali, dipakai pengerjaan pertama.
        "–" berarti tidak mengerjakan dan dihitung 0. Nilai akhir adalah rata-rata semua kuis,
        dikonversi ke huruf sesuai tabel konversi yang diatur admin.
    </p>
@endif
@endsection
