@extends('layouts.app')
@section('title', 'Backup Database')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Backup Database</h3>
    <form method="POST" action="{{ route('admin.backups.runNow') }}" onsubmit="return confirm('Jalankan backup sekarang? Proses ini bisa memakan beberapa detik.')">
        @csrf
        <button class="btn btn-primary">▶ Backup Sekarang</button>
    </form>
</div>

<div class="alert alert-info">
    Backup otomatis berjalan tiap hari jam 02:00 (server), menyimpan <strong>7 backup terakhir</strong>
    (yang lebih lama otomatis dihapus). File backup tersimpan di lokasi yang <strong>tidak bisa diakses
    publik lewat URL</strong> -- cuma bisa diunduh dari halaman ini oleh admin yang login.
</div>

<table class="table table-bordered bg-white">
    <thead>
        <tr>
            <th>Nama File</th>
            <th>Ukuran</th>
            <th>Tanggal</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($backups as $b)
            <tr>
                <td>{{ $b['filename'] }}</td>
                <td>
                    @php $kb = $b['size'] / 1024; @endphp
                    {{ $kb >= 1024 ? round($kb / 1024, 2) . ' MB' : round($kb, 1) . ' KB' }}
                </td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($b['modified'])->format('d-m-Y H:i') }}</td>
                <td>
                    <a href="{{ route('admin.backups.download', $b['filename']) }}" class="btn btn-sm btn-outline-success">⬇ Download</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">Belum ada backup. Klik "Backup Sekarang" untuk membuat yang pertama.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
