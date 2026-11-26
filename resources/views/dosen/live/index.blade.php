@extends('layouts.app')
@section('title', 'Kuis Interaktif')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Kuis Interaktif</h3>
    <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Saya</a>
</div>

<p class="text-muted">
    Latihan bergaya permainan dari kuis yang sudah ada. Peserta cukup scan QR dan mengisi nama, tanpa login.
    Hasilnya tidak masuk nilai semester. Untuk memulai, buka <strong>Kuis Saya</strong> lalu pilih <strong>Live</strong> atau <strong>Tantangan</strong> pada kuis.
</p>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h5>Live</h5>
            <p class="small text-muted mb-0">Anda memandu soal satu per satu di proyektor. Semua peserta menjawab bersamaan dengan hitung mundur, lalu muncul sebaran jawaban dan 5 besar.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h5>Tantangan</h5>
            <p class="small text-muted mb-0">Peserta mengerjakan sendiri dengan urutan acak dan langsung tahu benar atau salah. Papan peringkat bergerak di layar Anda. Cocok untuk latihan di luar kelas.</p>
        </div></div>
    </div>
</div>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
    <thead class="table-light">
        <tr><th>Waktu</th><th>Kuis</th><th>Mode</th><th>Kode</th><th>Peserta</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        @forelse ($sessions as $s)
            <tr>
                <td class="small">{{ $s->created_at->format('d-m-Y H:i') }}</td>
                <td>{{ $s->quiz->title ?? '(kuis dihapus)' }}</td>
                <td>{{ $s->modeLabel() }}</td>
                <td><code>{{ $s->code }}</code></td>
                <td>{{ $s->players_count }}</td>
                <td>{{ $s->isFinished() ? 'Selesai' : 'Berjalan' }}</td>
                <td class="text-nowrap">
                    @unless ($s->isFinished())
                        <a href="{{ route('dosen.live.show', $s) }}" class="btn btn-sm btn-primary">Buka layar</a>
                    @endunless
                    <a href="{{ route('dosen.live.results', $s) }}" class="btn btn-sm btn-outline-success">Hasil</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted">Belum ada sesi interaktif.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $sessions->links() }}
@endsection
