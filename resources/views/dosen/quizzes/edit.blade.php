@extends('layouts.app')
@section('title', 'Kelola Kuis')

@section('content')
<h3>{{ $quiz->title }}</h3>
<p class="text-muted">{{ $quiz->questions->count() }} soal tersimpan.</p>

<div class="card mb-4 border-primary">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small">Kode Kuis (bagikan ke mahasiswa)</div>
            <div class="fs-3 fw-bold" style="letter-spacing: 4px;">{{ $quiz->code }}</div>
            <div class="text-muted small mt-2" style="max-width: 320px;">
                Mahasiswa masuk ke menu "Masukkan Kode Kuis" lalu ketik kode ini,
                atau langsung scan QR code di samping lewat kamera HP.
            </div>
        </div>
        <div class="text-center">
            <img src="{{ route('dosen.quizzes.qrcode', $quiz) }}" alt="QR Code Kuis" width="160" height="160" class="border rounded p-2 bg-white">
            <a href="{{ route('dosen.quizzes.qrcode.download', $quiz) }}" class="btn btn-sm btn-outline-primary d-block mt-2">
                ⬇ Download QR Code
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Unggah Soal</div>
            <div class="card-body">
                <a href="{{ asset('templates/template-soal-kuis.xlsx') }}" class="btn btn-outline-success btn-sm mb-3" download>
                    ⬇ Download Template Excel (.xlsx)
                </a>

                @if ($quiz->course_id)
                    <a href="{{ route('dosen.quizzes.bank.picker', $quiz) }}" class="btn btn-outline-primary btn-sm mb-3">
                        📚 Tambah dari Bank Soal
                    </a>
                @endif

                <a href="{{ route('dosen.quizzes.questions.create', $quiz) }}" class="btn btn-outline-dark btn-sm mb-3">
                    ✏ Tambah Soal Manual (Esai/Isian/Gambar)
                </a>

                <form method="POST" action="{{ route('dosen.quizzes.import', $quiz) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.docx" class="form-control mb-2" required>
                    <button class="btn btn-primary btn-sm">Impor Soal</button>
                </form>
                <hr>
                <small class="text-muted">
                    <strong>Disarankan pakai .xlsx</strong> (lebih akurat, kecil kemungkinan salah format).
                    Download template di atas, isi sesuai kolom yang tersedia, lalu unggah kembali di sini.
                    Format .docx lama masih didukung tapi lebih rawan gagal parsing jika soal ditulis
                    memakai fitur numbering otomatis Word.
                    @unless ($quiz->course_id)
                        <br><strong>Kaitkan kuis ini ke mata kuliah</strong> (di Pengaturan Kuis) untuk bisa memakai Bank Soal.
                    @endunless
                </small>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Pengaturan Kuis</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dosen.quizzes.update', $quiz) }}">
                    @csrf @method('PUT')
                    <div class="mb-2">
                        <label class="form-label">Mata Kuliah</label>
                        <select name="course_id" class="form-control">
                            <option value="">— Umum / Tidak dikaitkan —</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" {{ $quiz->course_id == $course->id ? 'selected' : '' }}>{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Judul</label>
                        <input type="text" name="title" class="form-control" value="{{ $quiz->title }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Durasi (menit)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="{{ $quiz->duration_minutes }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Waktu Buka</label>
                        <input type="datetime-local" name="start_time" class="form-control" value="{{ optional($quiz->start_time)->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Waktu Tutup</label>
                        <input type="datetime-local" name="end_time" class="form-control" value="{{ optional($quiz->end_time)->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Maks. Percobaan</label>
                        <input type="number" name="max_attempts" class="form-control" value="{{ $quiz->max_attempts }}" required>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="shuffle_questions" value="1" class="form-check-input" {{ $quiz->shuffle_questions ? 'checked' : '' }}>
                        <label class="form-check-label">Acak soal</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="shuffle_options" value="1" class="form-check-input" {{ $quiz->shuffle_options ? 'checked' : '' }}>
                        <label class="form-check-label">Acak opsi jawaban</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_published" value="1" class="form-check-input" {{ $quiz->is_published ? 'checked' : '' }}>
                        <label class="form-check-label"><strong>Terbitkan kuis (mahasiswa bisa mengerjakan)</strong></label>
                    </div>
                    <button class="btn btn-success btn-sm">Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Daftar Soal</div>
            <ul class="list-group list-group-flush">
                @forelse ($quiz->questions as $q)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong>{{ $loop->iteration }}. {{ $q->question_text }}</strong>
                            <span class="badge {{ $q->type === 'esai' ? 'bg-warning text-dark' : ($q->type === 'isian_singkat' ? 'bg-info text-dark' : 'bg-secondary') }} flex-shrink-0 ms-2">
                                {{ ['pilihan_ganda' => 'PG', 'esai' => 'Esai', 'isian_singkat' => 'Isian'][$q->type] ?? $q->type }}
                            </span>
                        </div>
                        @if ($q->image_path)
                            <img src="{{ asset($q->image_path) }}" alt="Gambar soal" style="max-width: 150px; max-height: 100px;" class="my-2 border rounded d-block">
                        @endif
                        @if ($q->type === 'pilihan_ganda')
                            <ul class="mb-0">
                                @foreach ($q->options as $opt)
                                    <li class="{{ $opt->is_correct ? 'text-success fw-bold' : '' }}">
                                        {{ $opt->option_text }} @if($opt->is_correct) (benar) @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($q->type === 'isian_singkat')
                            <p class="mb-0 text-muted small">Jawaban benar: <strong>{{ $q->correct_text_answer }}</strong></p>
                        @else
                            <p class="mb-0 text-muted small fst-italic">Dinilai manual oleh dosen</p>
                        @endif

                        <div class="d-flex gap-2 mt-2">
                            @if ($q->type === 'pilihan_ganda')
                                <a href="{{ route('dosen.questions.edit', $q) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                            <form method="POST" action="{{ route('dosen.questions.destroy', $q) }}" onsubmit="return confirm('Hapus soal ini dari kuis?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Belum ada soal. Unggah file di sebelah kiri.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
