@extends('layouts.app')
@section('title', 'Kerjakan Kuis')

@section('content')

<div id="exam-watermark" aria-hidden="true">
    @php
        $watermarkText = auth()->user()->name . ' — ' . (auth()->user()->nim ?? '-') . ' — ' . now()->format('d-m-Y H:i');
    @endphp
    @for ($i = 0; $i < 40; $i++)
        <span>{{ $watermarkText }}</span>
    @endfor
</div>

<style>
    #exam-watermark {
        position: fixed; inset: 0; z-index: 9999; pointer-events: none; user-select: none;
        overflow: hidden; display: flex; flex-wrap: wrap; align-content: flex-start;
        gap: 40px 60px; padding: 20px; transform: rotate(-25deg) scale(1.3); opacity: 0.08;
    }
    #exam-watermark span { font-size: 14px; font-weight: 600; color: #000; white-space: nowrap; }
    .question-image { max-width: 100%; max-height: 350px; margin-bottom: 12px; border: 1px solid #dee2e6; border-radius: 4px; }

    /* Opsi jawaban -- seluruh kotak bisa diklik, bukan cuma bulatan radio kecil */
    .answer-option {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: border-color .15s, background-color .15s;
        background: #fff;
    }
    .answer-option:hover {
        border-color: #86b7fe;
        background: #f8f9ff;
    }
    .answer-option.is-selected {
        border-color: #0d6efd;
        background: #eaf2ff;
        font-weight: 600;
    }
    .answer-option input[type="radio"] {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        cursor: pointer;
        accent-color: #0d6efd;
    }
    .answer-option-text {
        flex-grow: 1;
        font-size: 1rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>{{ $attempt->quiz->title }}</h3>
    <div class="fs-4 fw-bold text-danger" id="timer">--:--</div>
</div>

<form id="quiz-form" method="POST" action="{{ route('mahasiswa.quizzes.submit', $attempt) }}"
      onsubmit="return confirm('Yakin ingin submit jawaban sekarang? Tindakan ini tidak bisa dibatalkan.')">
    @csrf

    @foreach ($questions as $i => $item)
        @php $q = $item['question']; @endphp
        <div class="card mb-3">
            <div class="card-body">
                <p class="fw-bold mb-3">{{ $i + 1 }}. {{ $q->question_text }}</p>

                @if ($q->image_path)
                    <img src="{{ asset($q->image_path) }}" alt="Gambar soal" class="question-image d-block">
                @endif

                @if ($q->type === 'pilihan_ganda')
                    @foreach ($item['options'] as $opt)
                        <label class="answer-option {{ $item['selected_option_id'] == $opt->id ? 'is-selected' : '' }}">
                            <input
                                type="radio"
                                class="answer-radio"
                                name="answer_{{ $item['answer_id'] }}"
                                value="{{ $opt->id }}"
                                data-answer-id="{{ $item['answer_id'] }}"
                                {{ $item['selected_option_id'] == $opt->id ? 'checked' : '' }}
                            >
                            <span class="answer-option-text">{{ $opt->option_text }}</span>
                        </label>
                    @endforeach
                @elseif ($q->type === 'isian_singkat')
                    <input
                        type="text"
                        class="form-control answer-text"
                        data-answer-id="{{ $item['answer_id'] }}"
                        value="{{ $item['answer_text'] }}"
                        placeholder="Ketik jawaban Anda..."
                    >
                @else
                    <textarea
                        class="form-control answer-text"
                        data-answer-id="{{ $item['answer_id'] }}"
                        rows="5"
                        placeholder="Tulis jawaban esai Anda di sini..."
                    >{{ $item['answer_text'] }}</textarea>
                    <small class="text-muted">Soal esai dinilai manual oleh dosen setelah Anda submit.</small>
                @endif
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary">Submit Jawaban</button>
    <span id="save-indicator" class="text-muted ms-2"></span>
</form>
@endsection

@section('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const saveUrl = "{{ route('mahasiswa.quizzes.saveAnswer', $attempt) }}";
    const indicator = document.getElementById('save-indicator');

    function handleSaveResponse(r) {
        if (r.status === 409) { window.location.reload(); return; }
        indicator.textContent = r.ok ? 'Tersimpan ✓' : 'Gagal menyimpan!';
    }

    // Auto-save pilihan ganda + highlight visual opsi yang dipilih
    document.querySelectorAll('.answer-radio').forEach(radio => {
        radio.addEventListener('change', function () {
            // Hapus highlight dari opsi lain di soal yang sama, lalu tandai yang baru dipilih
            const group = document.getElementsByName(this.name);
            group.forEach(r => r.closest('.answer-option').classList.remove('is-selected'));
            this.closest('.answer-option').classList.add('is-selected');

            indicator.textContent = 'Menyimpan...';
            fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ answer_id: this.dataset.answerId, option_id: this.value }),
            }).then(handleSaveResponse).catch(() => indicator.textContent = 'Gagal menyimpan, periksa koneksi!');
        });
    });

    // Auto-save esai & isian singkat -- reuse pola sama, debounce 800ms
    // supaya tidak kirim request tiap ketikan huruf.
    let debounceTimers = {};
    document.querySelectorAll('.answer-text').forEach(field => {
        field.addEventListener('input', function () {
            const answerId = this.dataset.answerId;
            clearTimeout(debounceTimers[answerId]);
            indicator.textContent = 'Mengetik...';
            debounceTimers[answerId] = setTimeout(() => {
                indicator.textContent = 'Menyimpan...';
                fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ answer_id: answerId, answer_text: this.value }),
                }).then(handleSaveResponse).catch(() => indicator.textContent = 'Gagal menyimpan, periksa koneksi!');
            }, 800);
        });
    });

    // Timer countdown
    const deadline = new Date("{{ $attempt->deadline_at->toIso8601String() }}").getTime();
    const timerEl = document.getElementById('timer');
    const interval = setInterval(() => {
        const remaining = deadline - Date.now();
        if (remaining <= 0) {
            clearInterval(interval);
            timerEl.textContent = '00:00';
            document.getElementById('quiz-form').removeAttribute('onsubmit');
            document.getElementById('quiz-form').submit();
            return;
        }
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        timerEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }, 1000);

    // Deteksi keluar tab
    const tabSwitchUrl = "{{ route('mahasiswa.quizzes.tabSwitch', $attempt) }}";
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            fetch(tabSwitchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                keepalive: true,
            }).catch(() => {});
        }
    });
</script>
@endsection
