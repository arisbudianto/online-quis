@extends('layouts.app')
@section('title', 'Bank Soal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3>Bank Soal: {{ $course->name }}</h3>
        <p class="text-muted mb-0">{{ $bankQuestions->total() }} soal tersimpan di bank ini.</p>
    </div>
    <a href="{{ route('dosen.courses.index') }}" class="btn btn-outline-secondary btn-sm">← Mata Kuliah</a>
</div>

<div class="card mb-4">
    <div class="card-header">Tambah Soal ke Bank</div>
    <div class="card-body">
        <a href="{{ asset('templates/template-soal-kuis.xlsx') }}" class="btn btn-outline-success btn-sm mb-3" download>
            ⬇ Download Template Excel (.xlsx)
        </a>
        <a href="{{ route('dosen.bank.createManual', $course) }}" class="btn btn-outline-dark btn-sm mb-3">
            ✏ Tambah Manual (Esai/Isian/Gambar)
        </a>

        <form method="POST" action="{{ route('dosen.bank.import', $course) }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".xlsx" class="form-control mb-2" required>
            <button class="btn btn-primary btn-sm">Import ke Bank</button>
        </form>
        <small class="text-muted d-block mt-2">
            Import Excel khusus untuk pilihan ganda. Untuk esai, isian singkat, atau soal
            bergambar, pakai tombol "Tambah Manual" di atas. Soal yang masuk ke bank TIDAK
            langsung masuk ke kuis manapun — nanti dipilih dari halaman "Kelola Kuis".
        </small>
    </div>
</div>

<div class="card">
    <div class="card-header">Daftar Soal di Bank</div>
    <ul class="list-group list-group-flush">
        @forelse ($bankQuestions as $bq)
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-start gap-2">
                            <strong>{{ $bq->question_text }}</strong>
                            <span class="badge {{ $bq->type === 'esai' ? 'bg-warning text-dark' : ($bq->type === 'isian_singkat' ? 'bg-info text-dark' : 'bg-secondary') }} flex-shrink-0">
                                {{ ['pilihan_ganda' => 'PG', 'esai' => 'Esai', 'isian_singkat' => 'Isian'][$bq->type] ?? $bq->type }}
                            </span>
                        </div>
                        @if ($bq->image_path)
                            <img src="{{ asset($bq->image_path) }}" alt="Gambar soal" style="max-width: 150px; max-height: 100px;" class="my-2 border rounded d-block">
                        @endif
                        @if ($bq->type === 'pilihan_ganda')
                            <ul class="mb-0">
                                @foreach ($bq->options as $opt)
                                    <li class="{{ $opt->is_correct ? 'text-success fw-bold' : '' }}">
                                        {{ $opt->option_text }} @if($opt->is_correct) (benar) @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($bq->type === 'isian_singkat')
                            <p class="mb-0 text-muted small">Jawaban benar: <strong>{{ $bq->correct_text_answer }}</strong></p>
                        @else
                            <p class="mb-0 text-muted small fst-italic">Dinilai manual oleh dosen</p>
                        @endif
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        @if ($bq->type === 'pilihan_ganda')
                            <a href="{{ route('dosen.bank.edit', $bq) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @endif
                        <form method="POST" action="{{ route('dosen.bank.destroy', $bq) }}" onsubmit="return confirm('Hapus soal ini dari bank? (Kuis yang sudah memakainya tidak ikut terhapus)')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </div>
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted">Belum ada soal di bank ini.</li>
        @endforelse
    </ul>
</div>

<div class="mt-3">
    {{ $bankQuestions->links() }}
</div>
@endsection
