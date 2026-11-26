@extends('live.layout')
@section('title', 'Tantangan ' . $session->code)
@section('body')
<div class="bar">
    <span>{{ $player->nickname }}</span>
    <small><span id="skor">{{ $player->score }}</span> poin</small>
</div>
<div class="wrap">
    <div id="v-start" class="kartu tengah">
        <p class="kecil">Tantangan</p>
        <h1>{{ $session->quiz->title ?? 'Kuis' }}</h1>
        <p>{{ $session->questionCount() }} soal · {{ $session->seconds_per_question }} detik per soal. Hasil langsung terlihat setelah tiap jawaban.</p>
        <button class="btn penuh" id="mulai">{{ $player->current_index > 0 ? 'Lanjutkan' : 'Mulai' }}</button>
    </div>

    <div id="v-question" class="hidden">
        <div class="kartu">
            <div style="display:flex;justify-content:space-between" class="kecil"><span id="nomor"></span><span class="timer" id="sisa"></span></div>
            <div class="timer-bar"><div id="timer-isi" style="width:100%"></div></div>
            <img id="gambar" class="soal-img hidden" alt="Gambar soal">
            <p class="soal" id="teks"></p>
        </div>
        <div class="opsi-grid" id="opsi"></div>
        <div id="umpan" class="kartu tengah hidden" style="margin-top:12px">
            <div class="status-besar" id="umpan-judul"></div>
            <p id="umpan-detail"></p>
            <button class="btn penuh" id="lanjut">Soal berikutnya</button>
        </div>
        <div id="galat" class="galat"></div>
    </div>

    <div id="v-finished" class="kartu tengah hidden">
        <h2 id="akhir-judul">Tantangan selesai</h2>
        <div class="status-besar" id="akhir-peringkat"></div>
        <p id="akhir-detail"></p>
        <div class="penghargaan" id="akhir-penghargaan"></div>
        <table class="papan" id="akhir-tabel" style="margin-top:12px"></table>
        <button class="btn putih penuh" id="segarkan" style="margin-top:12px">Perbarui peringkat</button>
    </div>

    <div id="v-kicked" class="kartu tengah hidden">
        <h2>Anda keluar dari sesi ini</h2>
        <p>Bergabung lagi dengan nama lain.</p>
        <a class="btn" href="{{ route('live.join', $session->code) }}">Gabung lagi</a>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const L = window.LIVE;
    const URLS = {
        question: @json(route('live.challenge.question', $session->code)),
        answer: @json(route('live.challenge.answer', $session->code)),
        board: @json(route('live.leaderboard', $session->code)),
        me: @json(route('live.me', $session->code)),
    };
    const TOTAL = {{ $session->questionCount() }};
    let cur = null, deadline = 0, timer = null, busy = false;
    const $ = id => document.getElementById(id);
    function show(v) { ['start', 'question', 'finished', 'kicked'].forEach(x => $('v-' + x).classList.toggle('hidden', x !== v)); }
    function fail(e) { if (e.status === 403) return show('kicked'); $('galat').textContent = e.message; }

    async function load() {
        $('galat').textContent = '';
        try {
            const d = await L.getJson(URLS.question);
            if (d.finished) return finished(d.closed);
            cur = d;
            show('question');
            const q = d.question;
            $('nomor').textContent = 'Soal ' + (d.index + 1) + ' dari ' + d.total;
            $('teks').textContent = q.text;
            const img = $('gambar');
            if (q.image) { img.src = q.image; img.classList.remove('hidden'); } else { img.classList.add('hidden'); }
            const box = $('opsi');
            box.innerHTML = '';
            box.classList.remove('hidden');
            q.options.forEach((o, i) => {
                const b = document.createElement('button');
                b.className = 'opsi o' + i;
                b.dataset.id = o.id;
                b.innerHTML = '<span class="huruf">' + L.letter(i) + '</span><span>' + L.esc(o.text) + '</span>';
                b.onclick = () => send(o.id);
                box.appendChild(b);
            });
            $('umpan').classList.add('hidden');
            deadline = Date.now() + d.remaining_ms;
            clearInterval(timer);
            timer = setInterval(tick, 250);
            tick();
        } catch (e) { fail(e); }
    }

    function tick() {
        const left = Math.max(0, deadline - Date.now());
        $('sisa').textContent = Math.ceil(left / 1000) + ' detik';
        $('timer-isi').style.width = (100 * left / (cur.seconds * 1000)) + '%';
        if (left <= 0) { clearInterval(timer); send(null); }
    }

    async function send(optionId) {
        if (busy || !cur) return;
        busy = true;
        clearInterval(timer);
        const buttons = $('opsi').querySelectorAll('button');
        buttons.forEach(b => b.disabled = true);
        try {
            const r = await L.post(URLS.answer, { question_id: cur.question.id, option_id: optionId });
            buttons.forEach(b => {
                const id = Number(b.dataset.id);
                if (id === r.correct_option_id) b.classList.add('benar');
                else b.classList.add('redup');
            });
            $('skor').textContent = r.score;
            const judul = $('umpan-judul');
            if (optionId === null) { judul.textContent = 'Waktu habis'; judul.className = 'status-besar merahteks'; }
            else if (r.correct) { judul.textContent = 'Benar! +' + r.points; judul.className = 'status-besar hijau'; }
            else { judul.textContent = 'Belum tepat'; judul.className = 'status-besar merahteks'; }
            $('umpan-detail').textContent = r.streak > 1 ? 'Beruntun ' + r.streak + ' jawaban benar' : '';
            $('lanjut').textContent = r.finished ? 'Lihat hasil' : 'Soal berikutnya';
            $('umpan').classList.remove('hidden');
        } catch (e) { fail(e); }
        busy = false;
    }

    async function finished(closed) {
        show('finished');
        clearInterval(timer);
        $('akhir-judul').textContent = closed ? 'Tantangan sudah ditutup' : 'Tantangan selesai';
        try {
            const b = await L.getJson(URLS.board);
            const me = await L.getJson(URLS.me);
            $('skor').textContent = me.score;
            $('akhir-peringkat').textContent = 'Peringkat ' + b.me.rank + ' dari ' + b.players;
            $('akhir-detail').textContent = me.score + ' poin · ' + me.correct + ' benar dari ' + TOTAL + ' soal' + (me.stars ? ' · ' + me.stars + ' bintang' : '');
            $('akhir-penghargaan').innerHTML = me.awards.length
                ? me.awards.map(t => '<div><div class="judul">Penghargaan</div><div class="nama">' + L.esc(t) + '</div></div>').join('') : '';
            $('akhir-tabel').innerHTML = L.boardRows(b.top, b.me.id);
        } catch (e) { fail(e); }
    }

    $('mulai').onclick = load;
    $('lanjut').onclick = load;
    $('segarkan').onclick = () => finished(false);
    @if ($player->finished_at || $session->isFinished()) finished({{ $session->isFinished() ? 'true' : 'false' }}); @endif
})();
</script>
@endsection
