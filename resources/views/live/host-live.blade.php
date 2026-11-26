@extends('live.layout')
@section('title', 'Layar Live ' . $session->code)
@section('body')
<div class="bar">
    <span>{{ $session->quiz->title ?? 'Kuis' }} <small>· Live · {{ $session->code }}</small></span>
    <span style="display:flex;gap:8px">
        <small id="info-peserta">0 peserta</small>
        <a class="btn putih" style="padding:6px 12px;font-size:13px" href="{{ route('dosen.live.index') }}">Keluar layar</a>
    </span>
</div>
<div class="wrap lebar">
    {{-- LOBBY --}}
    <div id="v-lobby" class="hidden">
        <div class="baris">
            <div class="kartu tengah">
                <p>Scan QR atau buka <strong>{{ preg_replace('#^https?://#', '', route('live.enter')) }}</strong></p>
                <img src="{{ route('dosen.live.qr', $session) }}" alt="QR gabung" style="width:100%;max-width:340px">
                <div class="kode-besar">{{ $session->code }}</div>
                <p class="kecil">Papan peringkat bersama: {{ preg_replace('#^https?://#', '', route('live.board', $session->code)) }}</p>
            </div>
            <div class="kartu">
                <h2><span id="jml-lobby">0</span> peserta</h2>
                <p class="kecil">Klik nama untuk mengeluarkan peserta (misal nama tidak pantas).</p>
                <div class="chips" id="daftar"></div>
                <div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn" id="mulai" disabled>Mulai kuis</button>
                    <span class="kecil" style="align-self:center">{{ $session->questionCount() }} soal · {{ $session->seconds_per_question }} detik per soal</span>
                </div>
            </div>
        </div>
    </div>

    {{-- SOAL --}}
    <div id="v-question" class="hidden">
        <div class="kartu">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span class="kecil" id="nomor"></span>
                <span class="timer" style="font-size:28px" id="sisa"></span>
            </div>
            <div class="timer-bar"><div id="timer-isi" style="width:100%"></div></div>
            <img id="gambar" class="soal-img hidden" alt="Gambar soal" style="max-height:340px">
            <p class="soal" id="teks" style="font-size:30px"></p>
        </div>
        <div class="opsi-grid" id="opsi"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;gap:12px;flex-wrap:wrap">
            <div class="status-besar"><span id="sudah">0</span> / <span id="total-peserta">0</span> menjawab</div>
            <button class="btn" id="tutup">Tutup soal sekarang</button>
        </div>
    </div>

    {{-- PEMBAHASAN --}}
    <div id="v-reveal" class="hidden">
        <div class="baris">
            <div>
                <div class="kartu">
                    <p class="kecil" id="r-nomor"></p>
                    <p class="soal" id="r-teks"></p>
                    <div id="r-opsi"></div>
                </div>
            </div>
            <div>
                <div class="kartu pemimpin" id="r-pemimpin"></div>
                <div class="kartu">
                    <h2>Jawaban benar: <span id="r-jml-benar">0</span> dari <span id="r-jml-peserta">0</span></h2>
                    <p class="kecil">Urut dari yang tercepat. Bintang untuk penjawab benar tercepat.</p>
                    <div class="chips" id="r-benar"></div>
                </div>
                <div class="kartu">
                    <h2>5 besar</h2>
                    <table class="papan" id="r-top"></table>
                    <button class="btn penuh" id="lanjut" style="margin-top:16px">Soal berikutnya</button>
                </div>
            </div>
        </div>
    </div>

    {{-- SELESAI --}}
    <div id="v-finished" class="hidden">
        <div class="kartu tengah">
            <h1>Hasil akhir</h1>
            <div class="podium" id="podium"></div>
            <h2 style="margin-top:8px">Penghargaan</h2>
            <div class="penghargaan" id="f-awards"></div>
            <table class="papan" id="f-top"></table>
            <div style="margin-top:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                <a class="btn" href="{{ route('dosen.live.results', $session) }}">Hasil lengkap</a>
                <a class="btn putih" href="{{ route('dosen.live.export', $session) }}">Unduh Excel</a>
            </div>
        </div>
    </div>

    <div style="text-align:right;margin-top:8px">
        <button class="btn putih" id="akhiri" style="padding:8px 14px;font-size:13px">Akhiri sesi</button>
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
        status: @json(route('dosen.live.status', $session)),
        start: @json(route('dosen.live.start', $session)),
        reveal: @json(route('dosen.live.reveal', $session)),
        next: @json(route('dosen.live.next', $session)),
        finish: @json(route('dosen.live.finish', $session)),
        kick: @json(url('dosen/interaktif/' . $session->id . '/keluarkan')),
    };
    let state = null, version = -1, timer = null, playerCount = 0, closing = false;
    const $ = id => document.getElementById(id);
    function show(v) { ['lobby', 'question', 'reveal', 'finished'].forEach(x => $('v-' + x).classList.toggle('hidden', x !== v)); }

    async function act(url, data) {
        try { await L.post(url, data); } catch (e) { console.log(e.message); }
        await pollState(true);
    }

    function renderQuestion(s) {
        const q = s.question;
        $('nomor').textContent = 'Soal ' + (s.index + 1) + ' dari ' + s.total;
        $('teks').textContent = q.text;
        const img = $('gambar');
        if (q.image) { img.src = q.image; img.classList.remove('hidden'); } else { img.classList.add('hidden'); }
        $('opsi').innerHTML = q.options.map((o, i) =>
            '<div class="opsi o' + i + '"><span class="huruf">' + L.letter(i) + '</span><span>' + L.esc(o.text) + '</span></div>').join('');
        closing = false;
        clearInterval(timer);
        timer = setInterval(() => tick(s), 250);
        tick(s);
    }

    function tick(s) {
        const left = Math.max(0, s.seconds * 1000 - (L.now() - s.started_ms));
        $('sisa').textContent = Math.ceil(left / 1000);
        $('timer-isi').style.width = (100 * left / (s.seconds * 1000)) + '%';
        if (left <= 0) { clearInterval(timer); closeQuestion(s.index); }
    }

    function closeQuestion(index) {
        if (closing) return;
        closing = true;
        act(URLS.reveal, { index: index });
    }

    function renderReveal(s) {
        clearInterval(timer);
        const q = s.question, r = s.reveal;
        $('r-nomor').textContent = 'Soal ' + (s.index + 1) + ' dari ' + s.total + ' · ' + r.answered + ' menjawab';
        $('r-teks').textContent = q.text;
        const max = Math.max(1, ...Object.values(r.counts));
        $('r-opsi').innerHTML = q.options.map((o, i) => {
            const n = r.counts[o.id] || 0, ok = o.id === r.correct_option_id;
            return '<div style="margin-bottom:10px"><div class="opsi o' + i + (ok ? ' benar' : ' redup') + '"><span class="huruf">' + L.letter(i) +
                '</span><span style="flex:1">' + L.esc(o.text) + '</span><span>' + n + '</span></div>' +
                '<div class="batang"><div class="o' + i + '" style="width:' + (100 * n / max) + '%"></div></div></div>';
        }).join('');
        $('r-top').innerHTML = L.boardRows(r.top);
        $('r-jml-benar').textContent = r.correct_count;
        $('r-jml-peserta').textContent = s.players;
        $('r-benar').innerHTML = r.correct_players.length
            ? r.correct_players.map((p, i) => '<span class="chip' + (i === 0 ? ' cepat' : '') + '">' + (i === 0 ? '<span class="bintang">&#9733;</span> ' : '') +
                L.esc(p.nickname) + ' <span class="kecil">' + String(p.seconds).replace('.', ',') + ' dtk</span></span>').join('')
            : '<span class="kecil">Belum ada yang menjawab benar.</span>';
        $('r-pemimpin').innerHTML = r.leader
            ? '<p class="kecil" style="margin:0">Pemimpin sementara</p><div class="nama">' + L.esc(r.leader.nickname) + L.stars(r.leader.stars) +
              '</div><p class="kecil" style="margin:0">' + r.leader.score + ' poin · ' + r.leader.correct + ' benar</p>'
            : '<p class="kecil" style="margin:0">Belum ada peserta.</p>';
        $('lanjut').textContent = s.index + 1 >= s.total ? 'Lihat hasil akhir' : 'Soal berikutnya';
    }

    function renderFinished(s) {
        clearInterval(timer);
        const t = s.top || [];
        const slot = (p, cls, label) => p
            ? '<div class="' + cls + '"><span class="kecil" style="display:block">' + label + '</span><span style="display:block;font-size:22px;margin:6px 0">' +
              L.esc(p.nickname) + L.stars(p.stars) + '</span><span class="kecil" style="display:block">' + p.score + ' poin</span></div>'
            : '<div class="' + cls + '"></div>';
        $('podium').innerHTML = slot(t[1], 'p2', 'Juara 2') + slot(t[0], 'p1', 'Juara 1') + slot(t[2], 'p3', 'Juara 3');
        $('f-top').innerHTML = L.boardRows(t.slice(3));
        // Juara 1-3 sudah di podium; di sini cukup penghargaan khusus.
        $('f-awards').innerHTML = L.awardCards((s.awards || []).filter(a => !a.key.startsWith('juara'))) || '<p class="kecil">Belum ada penghargaan khusus.</p>';
        $('akhiri').classList.add('hidden');
    }

    async function pollState(once) {
        try {
            const s = await L.getJson(STATE);
            if (s.v !== version) {
                const prev = state;
                version = s.v; state = s;
                show(s.status);
                if (s.status === 'question' && (!prev || prev.status !== 'question' || prev.index !== s.index)) renderQuestion(s);
                if (s.status === 'reveal') renderReveal(s);
                if (s.status === 'finished') renderFinished(s);
            }
        } catch (e) {}
        if (!once) setTimeout(() => pollState(false), 1000);
    }

    async function pollStatus() {
        try {
            const d = await L.getJson(URLS.status);
            playerCount = d.players.length;
            $('info-peserta').textContent = playerCount + ' peserta';
            $('jml-lobby').textContent = playerCount;
            $('total-peserta').textContent = playerCount;
            $('mulai').disabled = playerCount === 0;
            $('daftar').innerHTML = d.players.map(p => '<span class="chip">' + L.esc(p.nickname) +
                ' <button data-id="' + p.id + '" title="Keluarkan">keluarkan</button></span>').join('');
            if (d.answered !== null) {
                $('sudah').textContent = d.answered;
                if (state && state.status === 'question' && playerCount > 0 && d.answered >= playerCount) closeQuestion(state.index);
            }
        } catch (e) {}
        setTimeout(pollStatus, 2000);
    }

    $('daftar').addEventListener('click', e => {
        const id = e.target.dataset && e.target.dataset.id;
        if (id && confirm('Keluarkan peserta ini?')) act(URLS.kick + '/' + id);
    });
    $('mulai').onclick = () => act(URLS.start);
    $('tutup').onclick = () => closeQuestion(state.index);
    $('lanjut').onclick = () => act(URLS.next);
    $('akhiri').onclick = () => { if (confirm('Akhiri sesi sekarang? Peserta langsung melihat hasil akhir.')) act(URLS.finish); };

    L.syncClock(URLS.clock).then(() => { pollState(false); pollStatus(); });
})();
</script>
@endsection
