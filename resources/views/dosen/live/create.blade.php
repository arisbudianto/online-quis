@extends('layouts.app')
@section('title', 'Buat Sesi ' . ($mode === 'live' ? 'Live' : 'Tantangan'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <h3>{{ $mode === 'live' ? 'Mulai Live' : 'Buka Tantangan' }}</h3>
        <p class="text-muted mb-3">{{ $quiz->title }}</p>

        @if ($eligible === 0)
            <div class="alert alert-warning">
                Kuis ini belum punya soal pilihan ganda dengan kunci jawaban, jadi belum bisa dimainkan.
                <a href="{{ route('dosen.quizzes.edit', $quiz) }}">Kelola soal</a>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <p class="small text-muted">
                        {{ $eligible }} soal pilihan ganda akan dimainkan.
                        @if ($total > $eligible)
                            {{ $total - $eligible }} soal lain (esai, isian, atau tanpa kunci) dilewati.
                        @endif
                        Urutan pilihan jawaban selalu diacak. Hasil tidak masuk nilai semester.
                    </p>
                    <form method="POST" action="{{ route('dosen.live.store') }}">
                        @csrf
                        <input type="hidden" name="quiz_id" value="{{ $quiz->id }}">
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <div class="mb-3">
                            <label class="form-label">Waktu per soal</label>
                            <select name="seconds_per_question" class="form-select">
                                @foreach (\App\Models\LiveSession::SECONDS_OPTIONS as $sec)
                                    <option value="{{ $sec }}" @selected($sec === ($mode === 'live' ? 20 : 30))>{{ $sec }} detik</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $mode === 'live' ? 'Setelah waktu habis' : 'Setelah peserta menjawab' }}</label>
                            <select name="reveal_seconds" class="form-select">
                                @foreach (\App\Models\LiveSession::REVEAL_OPTIONS as $sec)
                                    <option value="{{ $sec }}" @selected($sec === ($mode === 'live' ? 5 : 3))>
                                        {{ $sec === 0 ? 'Manual: tunggu tombol Soal berikutnya' : 'Tampilkan pembahasan ' . $sec . ' detik, lalu lanjut otomatis' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($mode === 'live')
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="shuffle" value="1" id="shuffle">
                                <label class="form-check-label" for="shuffle">Acak urutan soal</label>
                            </div>
                        @else
                            <p class="small text-muted">Pada mode Tantangan urutan soal diacak untuk setiap peserta.</p>
                        @endif
                        @error('quiz') <div class="alert alert-danger">{{ $message }}</div> @enderror
                        <button class="btn btn-primary">{{ $mode === 'live' ? 'Buka layar live' : 'Buka tantangan' }}</button>
                        <a href="{{ route('dosen.quizzes.index') }}" class="btn btn-link">Batal</a>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
