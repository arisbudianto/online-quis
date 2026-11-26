@extends('layouts.app')
@section('title', 'Hasil Sesi ' . $session->code)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Hasil {{ $session->modeLabel() }}</h3>
        <div class="text-muted small">{{ $session->quiz->title ?? '(kuis dihapus)' }} · kode {{ $session->code }} · {{ $session->created_at->format('d-m-Y H:i') }}</div>
    </div>
    <div class="d-flex gap-2">
        @unless ($session->isFinished())
            <a href="{{ route('dosen.live.show', $session) }}" class="btn btn-primary btn-sm">Buka layar</a>
        @endunless
        <a href="{{ route('live.board', $session->code) }}" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm">Papan bersama</a>
        <a href="{{ route('dosen.live.export', $session) }}" class="btn btn-success btn-sm">Unduh Excel</a>
        <a href="{{ route('dosen.live.index') }}" class="btn btn-outline-secondary btn-sm">← Kuis Interaktif</a>
        <form method="POST" action="{{ route('dosen.live.destroy', $session) }}" class="d-inline"
              onsubmit="return confirm('Hapus sesi ini beserta semua peserta dan jawabannya? Tidak bisa dikembalikan.')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">Hapus</button>
        </form>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<p class="small text-muted">Latihan: hasil ini tidak masuk nilai semester.</p>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Peringkat ({{ $leaderboard->count() }} peserta)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Nama</th><th>NIM</th><th class="text-end">Benar</th><th class="text-end">Skor</th></tr></thead>
                    <tbody>
                        @forelse ($leaderboard as $i => $p)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $p->nickname }}</td>
                                <td class="small">{{ $p->user?->nim ?? '-' }}</td>
                                <td class="text-end">{{ $p->correct_count }}/{{ $session->questionCount() }}</td>
                                <td class="text-end fw-semibold">{{ $p->score }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada peserta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Per soal</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>No</th><th>Soal</th><th class="text-end">Benar</th><th class="text-end">Rata waktu</th></tr></thead>
                    <tbody>
                        @foreach ($perQuestion as $q)
                            <tr>
                                <td>{{ $q['no'] }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($q['text'], 70) }}</td>
                                <td class="text-end {{ $q['total'] > 0 && $q['benar'] / $q['total'] < 0.5 ? 'text-danger fw-semibold' : '' }}">
                                    {{ $q['benar'] }}/{{ $q['total'] }}
                                </td>
                                <td class="text-end small">{{ $q['rata_detik'] !== null ? $q['rata_detik'] . ' dtk' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Soal yang dijawab benar kurang dari separuh peserta ditandai merah.</div>
        </div>
    </div>
</div>
@endsection
