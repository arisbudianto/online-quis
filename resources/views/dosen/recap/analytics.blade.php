@extends('layouts.app')
@section('title', 'Analitik Semester')

@php
    $fmt = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    $params = fn () => array_filter(['periode' => $period?->id, 'mk' => $course?->id, 'kelas' => $kelas, 'angkatan' => $angkatan]);
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Analitik Semester</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('dosen.recap.index', $params()) }}" class="btn btn-outline-success btn-sm">📈 Rekap Nilai</a>
        <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Saya</a>
    </div>
</div>

<form method="GET" action="{{ route('dosen.recap.analytics') }}" class="row g-2 align-items-end mb-3">
    <div class="col-sm-3">
        <label class="form-label small mb-1">Periode</label>
        <select name="periode" class="form-select form-select-sm">
            @foreach ($periods as $p)
                <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->label }}{{ $p->is_active ? ' (aktif)' : '' }}</option>
            @endforeach
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
    <div class="alert alert-info">Pilih periode dan mata kuliah untuk melihat analitik.</div>
@elseif (!$stats)
    <div class="alert alert-warning">
        Analitik belum bisa disusun: pastikan mata kuliah ini punya kuis di {{ $period->label }}
        dan daftar peserta resmi sudah diisi
        (<a href="{{ route('dosen.enrollments.index', [$course, 'periode' => $period->id]) }}">kelola peserta</a>).
    </div>
@else
    @php $s = $stats['summary']['stats']; @endphp

    <p class="text-muted small mb-3">
        {{ $course->name }} · {{ $period->label }}{{ $kelas ? ' · Kelas ' . $kelas : '' }}{{ $angkatan ? ' · Angkatan ' . \App\Support\Angkatan::label($angkatan) : '' }} ·
        {{ $stats['summary']['quizzes'] }} kuis · {{ $stats['summary']['participants'] }} peserta
        @if ($stats['summary']['pending'] > 0)
            · <span class="text-warning">{{ $stats['summary']['pending'] }} belum final (esai belum dinilai), tidak ikut statistik nilai akhir</span>
        @endif
    </p>

    {{-- Ringkasan nilai akhir --}}
    <div class="row g-2 mb-4">
        @foreach ([
            ['Rata-rata', $s['mean']], ['Median', $s['median']], ['Simpangan baku', $s['sd']],
            ['Terendah', $s['min']], ['Tertinggi', $s['max']], ['Q1 – Q3', null],
        ] as [$label, $value])
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-semibold">
                            {{ $label === 'Q1 – Q3' ? $fmt($s['q1']) . ' – ' . $fmt($s['q3']) : $fmt($value) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Distribusi Nilai Huruf ({{ $stats['summary']['final_count'] }} final)</div>
                <div class="card-body">
                    <canvas id="letterChart" height="230"></canvas>
                    <table class="table table-sm mt-3 mb-0">
                        <tbody>
                            @foreach ($stats['letters'] as $l)
                                <tr>
                                    <td class="fw-bold" style="width:3rem">{{ $l['letter'] }}</td>
                                    <td>{{ $l['count'] }} mahasiswa</td>
                                    <td class="text-end text-muted">{{ $fmt($l['percent']) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Tren Antar Kuis</div>
                <div class="card-body">
                    <canvas id="trendChart" height="230"></canvas>
                    <p class="small text-muted mt-2 mb-0">
                        Garis biru: rata-rata skor yang mengerjakan. Batang abu: persentase peserta yang mengerjakan.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Per kuis --}}
    <div class="card mb-4">
        <div class="card-header">Statistik per Kuis</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kuis</th>
                        <th class="text-center">Mengerjakan</th>
                        <th class="text-center">Partisipasi</th>
                        <th class="text-center">Rata-rata</th>
                        <th class="text-center">Median</th>
                        <th class="text-center">SB</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Maks</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats['perQuiz'] as $q)
                        <tr>
                            <td>
                                {{ $q['title'] }}
                                @if ($q['pending'] > 0)
                                    <span class="badge bg-warning text-dark" title="Menunggu nilai esai">{{ $q['pending'] }} esai</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $q['attempted'] }} / {{ $stats['summary']['participants'] }}</td>
                            <td class="text-center {{ $q['participation'] < 75 ? 'text-danger fw-semibold' : '' }}">{{ $fmt($q['participation']) }}%</td>
                            <td class="text-center">{{ $fmt($q['stats']['mean']) }}</td>
                            <td class="text-center">{{ $fmt($q['stats']['median']) }}</td>
                            <td class="text-center">{{ $fmt($q['stats']['sd']) }}</td>
                            <td class="text-center">{{ $fmt($q['stats']['min']) }}</td>
                            <td class="text-center">{{ $fmt($q['stats']['max']) }}</td>
                            <td class="text-center">
                                <a href="{{ route('dosen.quizzes.analytics', $q['id']) }}" class="small">detail soal</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Statistik per kuis dihitung dari yang mengerjakan (nilai final). SB = simpangan baku.
            Partisipasi di bawah 75% ditandai merah.
        </div>
    </div>

    {{-- Per kelas --}}
    @if (count($stats['perKelas']) > 1 || !$kelas)
        <div class="card mb-4">
            <div class="card-header">Perbandingan Kelas (nilai akhir)</div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kelas</th>
                            <th class="text-center">Peserta</th>
                            <th class="text-center">Rata-rata</th>
                            <th class="text-center">Median</th>
                            <th class="text-center">SB</th>
                            <th class="text-center">Partisipasi</th>
                            <th class="text-center">Di bawah C</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['perKelas'] as $k)
                            <tr>
                                <td class="fw-semibold">{{ $k['kelas'] }}</td>
                                <td class="text-center">{{ $k['participants'] }}</td>
                                <td class="text-center">{{ $fmt($k['stats']['mean']) }}</td>
                                <td class="text-center">{{ $fmt($k['stats']['median']) }}</td>
                                <td class="text-center">{{ $fmt($k['stats']['sd']) }}</td>
                                <td class="text-center">{{ $fmt($k['participation']) }}%</td>
                                <td class="text-center">{{ $k['below_c'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Perlu perhatian --}}
    <div class="card mb-4">
        <div class="card-header">
            Perlu Perhatian ({{ count($stats['attention']) }})
        </div>
        @if (count($stats['attention']) === 0)
            <div class="card-body text-muted">Semua peserta mengerjakan seluruh kuis dan nilai akhirnya minimal C.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>NIM</th><th>Nama</th><th>Kelas</th><th>Angkatan</th><th class="text-center">Nilai Akhir</th><th class="text-center">Huruf</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['attention'] as $a)
                            <tr>
                                <td>{{ $a['student']->nim }}</td>
                                <td>{{ $a['student']->name }}</td>
                                <td>{{ $a['student']->kelas }}</td>
                                <td>{{ $a['student']->angkatan ?? 'Lainnya' }}</td>
                                <td class="text-center">{{ $fmt($a['final']) }}</td>
                                <td class="text-center fw-bold">{{ $a['letter'] }}</td>
                                <td class="small">
                                    @if ($a['below_c']) <span class="badge bg-danger">di bawah C</span> @endif
                                    @if ($a['missed'] > 0) <span class="badge bg-secondary">tidak mengerjakan {{ $a['missed'] }} kuis</span> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="card-footer small text-muted">
            Batas "di bawah C" mengikuti tabel konversi nilai saat ini (C ≥ {{ $fmt($stats['cThreshold']) }}).
            Kuis yang tidak dikerjakan dihitung 0 pada nilai akhir.
        </div>
    </div>
@endif
@endsection

@section('scripts')
@if ($stats)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('letterChart'), {
        type: 'bar',
        data: {
            labels: @json(collect($stats['letters'])->pluck('letter')),
            datasets: [{ label: 'Mahasiswa', data: @json(collect($stats['letters'])->pluck('count')), backgroundColor: '#0d6efd' }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('trendChart'), {
        data: {
            labels: @json(collect($stats['perQuiz'])->pluck('title')->map(fn ($t) => \Illuminate\Support\Str::limit($t, 18))),
            datasets: [
                { type: 'line', label: 'Rata-rata skor', data: @json(collect($stats['perQuiz'])->pluck('stats.mean')),
                  borderColor: '#0d6efd', backgroundColor: '#0d6efd', yAxisID: 'y', tension: 0 },
                { type: 'bar', label: 'Partisipasi (%)', data: @json(collect($stats['perQuiz'])->pluck('participation')),
                  backgroundColor: 'rgba(108,117,125,.35)', yAxisID: 'y' }
            ]
        },
        options: { scales: { y: { min: 0, max: 100 } } }
    });
</script>
@endif
@endsection
