@extends('layouts.app')
@section('title', 'Rekap Nilai')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Nilai: {{ $quiz->title }}</h3>
    <div class="d-flex gap-2">
        @if ($quiz->questions()->where('type', 'esai')->exists())
            <a href="{{ route('dosen.grading.index', $quiz) }}" class="btn btn-warning">📝 Nilai Esai</a>
        @endif
        <a href="{{ route('dosen.quizzes.analytics', $quiz) }}" class="btn btn-info">📊 Lihat Analitik</a>
        <a href="{{ route('dosen.quizzes.scores.export', $quiz) }}" class="btn btn-success">Ekspor ke Excel (.xlsx)</a>
    </div>
</div>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>#</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Kelas</th>
            <th>Skor</th>
            <th>Status</th>
            <th>Tab Switch</th>
            <th>IP / Device</th>
            <th>Waktu Submit</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($attempts as $a)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $a->user->nim }}</td>
                <td>{{ $a->user->name }}</td>
                <td>{{ $a->user->kelas ?? '-' }}</td>
                <td>
                    @if ($a->status === 'submitted' && $a->needs_grading)
                        <span class="text-muted fst-italic">Menunggu esai</span>
                    @elseif ($a->status === 'submitted')
                        {{ $a->score }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    @if ($a->status === 'submitted' && $a->needs_grading)
                        <span class="badge bg-warning text-dark">Menunggu Nilai Esai</span>
                    @elseif ($a->status === 'submitted')
                        <span class="badge bg-success">Submitted</span>
                    @elseif ($a->status === 'in_progress')
                        <span class="badge bg-warning text-dark">Sedang Mengerjakan</span>
                    @else
                        <span class="badge bg-secondary">Expired</span>
                    @endif
                </td>
                <td>
                    @if ($a->tab_switch_count > 0)
                        <span class="badge bg-danger" title="Jumlah kali keluar tab/window selama mengerjakan">
                            ⚠ {{ $a->tab_switch_count }}x
                        </span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
                <td>
                    <small class="text-muted" title="{{ $a->user_agent }}">
                        {{ $a->ip_address ?? '-' }}
                    </small>
                </td>
                <td>{{ optional($a->submitted_at)->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="d-flex gap-2 flex-wrap">
                    @if ($a->status === 'in_progress' && $a->browser_session_id)
                        <form method="POST" action="{{ route('dosen.attempts.resetDevice', $a) }}" onsubmit="return confirm('Reset device untuk {{ $a->user->name }}? Mahasiswa akan bisa lanjut mengerjakan dari perangkat manapun (percobaan tetap terhitung).')">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning">🔓 Reset Device</button>
                        </form>
                    @endif
                    @if ($a->status === 'in_progress')
                        <form method="POST" action="{{ route('dosen.attempts.cancel', $a) }}" onsubmit="return confirm('Batalkan percobaan {{ $a->user->name }}? Attempt ini akan DIHAPUS sepenuhnya dan mahasiswa bisa mulai kuis dari awal lagi (slot percobaan bebas). Pakai ini kalau attempt-nya nyangkut karena masalah koneksi/device dan tidak akan pernah bisa dilanjutkan.')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">🔄 Batalkan & Izinkan Ulang</button>
                        </form>
                    @endif
                    @if ($a->needs_grading)
                        <a href="{{ route('dosen.grading.edit', $a) }}" class="btn btn-sm btn-outline-primary">Nilai Esai</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted">Belum ada mahasiswa yang mengerjakan.</td></tr>
        @endforelse
    </tbody>
</table>

<small class="text-muted">
    Kolom "Tab Switch" mencatat berapa kali mahasiswa keluar dari tab/window selama mengerjakan
    (bukan diskualifikasi otomatis -- murni informasi untuk Anda tindak lanjuti sendiri).
    Status "Menunggu Nilai Esai" berarti skor akhir belum final sampai semua soal esai di attempt itu dinilai.
    <br>
    <strong>Reset Device</strong> = lepaskan ikatan perangkat, mahasiswa lanjut dari device lain (percobaan tetap terhitung).
    <strong>Batalkan & Izinkan Ulang</strong> = hapus percobaan yang nyangkut sepenuhnya, slot percobaan kembali bebas
    (pakai ini kalau attempt-nya memang tidak mungkin dilanjutkan, misal koneksi mahasiswa putus total).
</small>
@endsection
