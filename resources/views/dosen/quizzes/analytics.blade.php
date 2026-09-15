@extends('layouts.app')
@section('title', 'Analitik Kuis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3>Analitik: {{ $quiz->title }}</h3>
        <p class="text-muted mb-0">{{ $totalSubmitted }} mahasiswa sudah submit.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dosen.quizzes.scores', $quiz) }}" class="btn btn-outline-secondary btn-sm">← Nilai</a>
        <a href="{{ route('dosen.quizzes.edit', $quiz) }}" class="btn btn-outline-secondary btn-sm">Kelola Kuis</a>
    </div>
</div>

@if ($totalSubmitted === 0)
    <div class="alert alert-warning">Belum ada mahasiswa yang submit kuis ini, analitik belum tersedia.</div>
@else

@if ($pendingGradingCount > 0)
    <div class="alert alert-info">
        {{ $pendingGradingCount }} attempt masih menunggu penilaian esai — grafik di bawah ini
        (distribusi skor & perbandingan kelas) baru menghitung attempt yang skornya sudah final.
        <a href="{{ route('dosen.grading.index', $quiz) }}">Nilai esai sekarang →</a>
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">Distribusi Skor</div>
            <div class="card-body">
                <canvas id="distChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                @if ($showClassComparison) Perbandingan Antar Kelas @else Rata-rata Skor Kelas @endif
            </div>
            <div class="card-body">
                @if ($showClassComparison)
                    <canvas id="classChart" height="220"></canvas>
                @else
                    @php $only = $classStats->first(); @endphp
                    @if ($only)
                        <div class="text-center py-4">
                            <div class="display-4 fw-bold">{{ round($only->avg_score, 1) }}</div>
                            <p class="text-muted mb-0">Rata-rata Kelas {{ $only->kelas ?? '(tanpa kelas)' }} &middot; {{ $only->total }} mahasiswa</p>
                        </div>
                    @else
                        <p class="text-muted mb-0">Data kelas mahasiswa tidak tersedia.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Soal Tersulit (diurutkan dari paling banyak salah)</div>
    <table class="table table-bordered mb-0">
        <thead>
            <tr>
                <th style="width: 50%">Pertanyaan</th>
                <th>Benar / Dijawab</th>
                <th>% Benar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($questionAnalytics as $qa)
                <tr>
                    <td>{{ \Illuminate\Support\Str::limit($qa['question_text'], 120) }}</td>
                    <td>{{ $qa['total_correct'] }} / {{ $qa['total_answered'] }}</td>
                    <td>
                        @if (is_null($qa['percentage']))
                            <span class="text-muted">-</span>
                        @else
                            <span class="badge {{ $qa['percentage'] < 50 ? 'bg-danger' : ($qa['percentage'] < 75 ? 'bg-warning text-dark' : 'bg-success') }}">
                                {{ $qa['percentage'] }}%
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($essayStats->isNotEmpty())
<div class="card mt-4">
    <div class="card-header">Rata-rata Poin Soal Esai (yang sudah dinilai)</div>
    <table class="table table-bordered mb-0">
        <thead>
            <tr>
                <th style="width: 60%">Pertanyaan</th>
                <th>Rata-rata Poin</th>
                <th>Jumlah Dinilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($essayStats as $es)
                <tr>
                    <td>{{ \Illuminate\Support\Str::limit($es->question_text, 120) }}</td>
                    <td>{{ round($es->avg_points, 1) }}</td>
                    <td>{{ $es->total_graded }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endif
@endsection

@section('scripts')
@if ($totalSubmitted > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('distChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($buckets)) !!},
            datasets: [{
                label: 'Jumlah Mahasiswa',
                data: {!! json_encode(array_values($buckets)) !!},
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    @if ($showClassComparison)
    new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($classStats->map(fn($c) => 'Kelas ' . ($c->kelas ?? '-'))->all()) !!},
            datasets: [{
                label: 'Rata-rata Skor',
                data: {!! json_encode($classStats->map(fn($c) => round($c->avg_score, 1))->all()) !!},
                backgroundColor: '#198754'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
    @endif
</script>
@endif
@endsection
