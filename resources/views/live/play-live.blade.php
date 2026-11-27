@extends('live.layout')
@section('title', 'Live ' . $session->code)
@section('body')
<div class="bar">
    <span id="nama">{{ $player->nickname }}</span>
    <small><span id="skor">{{ $player->score }}</span> poin</small>
</div>
<div class="wrap">
    <div id="v-lobby" class="kartu tengah">
        <h2>Anda sudah masuk</h2>
        <p>Tunggu dosen memulai kuis. Layar ini berganti sendiri.</p>
        <p class="kecil"><span id="jml-peserta">0</span> peserta bergabung</p>
    </div>

    <div id="v-question" class="hidden">
        <div class="kartu">
            <div style="display:flex;justify-content:space-between" class="kecil"><span id="nomor"></span><span class="timer" id="sisa"></span></div>
            <div class="timer-bar"><div id="timer-isi" style="width:100%"></div></div>
            <img id="gambar" class="soal-img hidden" alt="Gambar soal">
            <p class="soal" id="teks"></p>
        </div>
        <div class="opsi-grid" id="opsi"></div>
        <div id="terkirim" class="kartu tengah hidden" style="margin-top:12px">
            <div class="status-besar">Jawaban terkirim</div>
            <p>Tunggu soal ditutup untuk melihat hasilnya.</p>
        </div>
        <div id="galat-jawab" class="galat"></div>
    </div>

    <div id="v-reveal" class="kartu tengah hidden">
        <div class="status-besar" id="hasil-judul"></div>
        <p id="hasil-detail"></p>
        <p id="hasil-bintang" class="bintang"></p>
        <p class="kecil" id="hasil-pemimpin"></p>
        <p class="kecil" id="hasil-kunci"></p>
        <p class="kecil" id="hasil-lanjut"></p>
    </div>

    <div id="v-finished" class="kartu tengah hidden">
        <h2>Kuis selesai</h2>
        <div class="status-besar" id="akhir-peringkat"></div>
        <p id="akhir-detail"></p>
        <div class="penghargaan" id="akhir-penghargaan"></div>
        <table class="papan" id="akhir-tabel" style="margin-top:12px"></table>
    </div>

    <div id="v-kicked" class="kartu tengah hidden">
        <h2>Anda keluar dari sesi ini</h2>
        <p>Dosen mengeluarkan nama ini dari sesi. Bergabung lagi dengan nama lain.</p>
        <a class="btn" href="{{ route('live.join', $session->code) }}">Gabung lagi</a>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const L = window.LIVE;
    const STATE = @json($stateUrl);
    const URLS = {
        clock: @json(route('live.clock')),
        answer: @json(route('live.answer', $session->code)),
        me: @json(route('live.me', $session->code)),
    };
    const POLL = {{ $pollMs }};
    let version = -1, current = null, answeredFor = null, shownAt = null, revealFor = null, timerHandle = null;

    const $ = id => document.getElementById(id);
    function show(view) {
        ['lobby', 'question', 'reveal', 'finished', 'kicked'].forEach(v => $('v-' + v).classList.toggle('hidden', v !== view));
    }

    function renderQuestion(s) {
        const q = s.question;
        $('nomor').textContent = 'Soal ' + (s.index + 1) + ' dari ' + s.total;
        $('teks').textContent = q.text;
        const img = $('gambar');
        if (q.image) { img.src = q.image; img.classList.remove('hidden'); } else { img.classList.add('hidden'); }
        const box = $('opsi');
        box.innerHTML = '';
        q.options.forEach((o, i) => {
            const b = document.createElement('button');
            b.className = 'opsi o' + i;
            b.innerHTML = '<span class="huruf">' + L.letter(i) + '</span><span>' + L.esc(o.text) + '</span>';
            b.onclick = () => answer(q.id, o.id, b);
            box.appendChild(b);
        });
        const done = answeredFor === q.id;
        box.classList.toggle('hidden', done);
        $('terkirim').classList.toggle('hidden', !done);
        $('galat-jawab').textContent = '';
        shownAt = performance.now();
        clearInterval(timerHandle);
        timerHandle = setInterval(() => tick(s), 250);
        tick(s);
    }

    function tick(s) {
        const left = Math.max(0, s.seconds * 1000 - (L.now() - s.started_ms));
        $('sisa').textContent = Math.ceil(left / 1000) + ' detik';
        $('timer-isi').style.width = (100 * left / (s.seconds * 1000)) + '%';
        if (left <= 0) {
            clearInterval(timerHandle);
            if (answeredFor !== s.question.id) {
                $('opsi').querySelectorAll('button').forEach(b => b.disabled = true);
            }
        }
    }

    async function answer(qid, oid, btn) {
        if (answeredFor === qid) return;
        answeredFor = qid;
        $('opsi').querySelectorAll('button').forEach(b => { b.disabled = true; if (b !== btn) b.classList.add('redup'); });
        try {
            await L.post(URLS.answer, { question_id: qid, option_id: oid, elapsed_ms: Math.round(performance.now() - shownAt) });
            $('opsi').classList.add('hidden');
            $('terkirim').classList.remove('hidden');
        } catch (e) {
            if (e.status === 403) return show('kicked');
            $('galat-jawab').textContent = e.message;
        }
    }

    async function renderReveal(s) {
        if (revealFor === s.question.id) return;
        revealFor = s.question.id;
        show('reveal');
        clearInterval(window.__lanjut);
        $('hasil-lanjut').textContent = '';
        if (s.reveal_seconds) {
            let left = s.reveal_seconds;
            const akhir = s.index + 1 >= s.total;
            const tulis = () => $('hasil-lanjut').textContent = (akhir ? 'Hasil akhir' : 'Soal berikutnya') + ' dalam ' + left + ' detik';
            tulis();
            window.__lanjut = setInterval(() => { left--; if (left <= 0) { clearInterval(window.__lanjut); $('hasil-lanjut').textContent = ''; } else tulis(); }, 1000);
        }
        const kunci = s.question.options.findIndex(o => o.id === s.reveal.correct_option_id);
        $('hasil-kunci').textContent = 'Jawaban benar: ' + L.letter(kunci) + '. ' + (s.question.options[kunci] ? s.question.options[kunci].text : '');
        $('hasil-judul').textContent = '...';
        $('hasil-detail').textContent = '';
        try {
            const me = await L.getJson(URLS.me + '?question_id=' + s.question.id);
            $('skor').textContent = me.score;
            if (!me.last.answered) {
                $('hasil-judul').textContent = 'Tidak menjawab';
                $('hasil-judul').className = 'status-besar merahteks';
            } else if (me.last.correct) {
                $('hasil-judul').textContent = 'Benar! +' + me.last.points;
                $('hasil-judul').className = 'status-besar hijau';
            } else {
                $('hasil-judul').textContent = 'Belum tepat';
                $('hasil-judul').className = 'status-besar merahteks';
            }
            $('hasil-detail').textContent = 'Peringkat ' + me.rank + ' dari ' + me.players + (me.streak > 1 ? ' · beruntun ' + me.streak : '');
            $('hasil-bintang').innerHTML = me.fastest ? '&#9733; Anda penjawab benar tercepat! (' + me.stars + ' bintang)' : (me.stars > 0 ? '&#9733; ' + me.stars + ' bintang' : '');
            $('hasil-pemimpin').textContent = s.reveal.leader ? 'Pemimpin sementara: ' + s.reveal.leader.nickname + ' (' + s.reveal.leader.score + ' poin)' : '';
        } catch (e) {
            if (e.status === 403) show('kicked');
        }
    }

    async function renderFinished(s) {
        show('finished');
        try {
            const me = await L.getJson(URLS.me);
            $('skor').textContent = me.score;
            $('akhir-peringkat').textContent = 'Peringkat ' + me.rank + ' dari ' + me.players;
            $('akhir-detail').textContent = me.score + ' poin · ' + me.correct + ' jawaban benar dari ' + s.total + ' soal' + (me.stars ? ' · ' + me.stars + ' bintang' : '');
            const mine = (s.awards || []).filter(a => me.awards.includes(a.title));
            $('akhir-penghargaan').innerHTML = mine.length ? L.awardCards(mine) : '';
        } catch (e) {
            if (e.status === 403) return show('kicked');
        }
        $('akhir-tabel').innerHTML = L.boardRows(s.top || []);
    }

    async function poll() {
        try {
            const s = await L.getJson(STATE);
            $('jml-peserta').textContent = s.players;
            if (s.v !== version) {
                version = s.v;
                if (s.status === 'lobby') show('lobby');
                else if (s.status === 'question') {
                    if (!current || current.index !== s.index || current.status !== 'question') {
                        show('question');
                        renderQuestion(s);
                    }
                } else if (s.status === 'reveal') { clearInterval(timerHandle); await renderReveal(s); }
                else if (s.status === 'finished') { clearInterval(timerHandle); await renderFinished(s); return; }
                current = s;
            }
        } catch (e) { /* jaringan putus sebentar: coba lagi */ }
        setTimeout(poll, POLL);
    }

    L.syncClock(URLS.clock).then(poll);
})();
</script>
@endsection
