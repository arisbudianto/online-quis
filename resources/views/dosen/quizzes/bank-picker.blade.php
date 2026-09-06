@extends('layouts.app')
@section('title', 'Tambah dari Bank Soal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3>Tambah Soal dari Bank ke: {{ $quiz->title }}</h3>
        <p class="text-muted mb-0">Mata kuliah: {{ $quiz->course->name }} &middot; {{ $bankQuestions->count() }} soal tersedia di bank.</p>
    </div>
    <a href="{{ route('dosen.quizzes.edit', $quiz) }}" class="btn btn-outline-secondary btn-sm">← Kembali ke Kuis</a>
</div>

<form method="POST" action="{{ route('dosen.quizzes.bank.add', $quiz) }}">
    @csrf

    <div class="card mb-3">
        <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label class="form-label mb-1">Atau ambil otomatis secara acak</label>
                <input type="number" name="random_count" class="form-control" style="width: 140px" min="1" placeholder="Jumlah soal">
            </div>
            <small class="text-muted mb-2">Soal acak akan ditambahkan selain soal yang Anda centang manual di bawah (kalau ada).</small>
        </div>
    </div>

    @if ($bankQuestions->isEmpty())
        <div class="alert alert-warning">
            Bank soal untuk mata kuliah ini masih kosong.
            <a href="{{ route('dosen.bank.index', $quiz->course_id) }}">Import soal ke bank dulu di sini</a>.
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Pilih soal secara manual</span>
                <a href="{{ route('dosen.bank.index', $quiz->course_id) }}" class="small">Kelola bank soal ini →</a>
            </div>
            <ul class="list-group list-group-flush">
                @foreach ($bankQuestions as $bq)
                    <li class="list-group-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bank_question_ids[]" value="{{ $bq->id }}" id="bq{{ $bq->id }}">
                            <label class="form-check-label" for="bq{{ $bq->id }}">
                                <strong>{{ $bq->question_text }}</strong>
                                <ul class="mb-0 mt-1">
                                    @foreach ($bq->options as $opt)
                                        <li class="{{ $opt->is_correct ? 'text-success' : '' }}">{{ $opt->option_text }}</li>
                                    @endforeach
                                </ul>
                            </label>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <button type="submit" class="btn btn-primary mt-3">Tambahkan ke Kuis</button>
</form>
@endsection
