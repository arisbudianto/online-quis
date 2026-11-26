@extends('live.layout')
@section('title', 'Papan ' . $session->code)
@section('body')
<style>
    body.papan { background: radial-gradient(1200px 600px at 10% -10%, #7a45c2 0%, transparent 60%),
                             radial-gradient(900px 500px at 110% 10%, #c2457a 0%, transparent 55%),
                             linear-gradient(160deg, #2b1055 0%, #3d1a78 50%, #5b2a9b 100%);
                 min-height: 100vh; color: #fff; }
    .pp-wrap { max-width: 1280px; margin: 0 auto; padding: 20px 16px 40px; }
    .pp-head { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; margin-bottom: 28px; }
    .pp-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .pp-logo { width: 44px; height: 44px; border-radius: 12px; background: #fff; color: var(--ungu); display: grid; place-items: center; font: 800 18px "Poppins", sans-serif; box-shadow: 0 4px 0 rgba(0,0,0,.25); flex: none; }
    .pp-title { font: 700 22px/1.2 "Poppins", sans-serif; margin: 0; overflow-wrap: anywhere; color: #fff; }
    .pp-sub { font-size: 14px; opacity: .8; }
    .pp-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .pp-chip { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); padding: 8px 14px; border-radius: 99px; font-weight: 700; font-size: 14px; display: inline-flex; gap: 8px; align-items: center; }
    .pp-dot { width: 9px; height: 9px; border-radius: 50%; background: #3ddc84; animation: pp-pulse 1.6s infinite; }
    .pp-dot.mati { background: #c7c2cf; animation: none; }
    @keyframes pp-pulse { 0% { box-shadow: 0 0 0 0 rgba(61,220,132,.6); } 70% { box-shadow: 0 0 0 10px rgba(61,220,132,0); } 100% { box-shadow: 0 0 0 0 rgba(61,220,132,0); } }
    .pp-progress { width: 140px; height: 8px; border-radius: 99px; background: rgba(255,255,255,.2); overflow: hidden; }
    .pp-progress > div { height: 100%; background: linear-gradient(90deg, #ffd166, #f2a900); width: 0; transition: width .6s ease; }

    .pp-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media (min-width: 980px) { .pp-grid { grid-template-columns: 1fr 300px; } }

    .pp-card { background: #fff; color: var(--hitam); border-radius: 20px; box-shadow: 0 8px 0 rgba(0,0,0,.18); padding: 20px; }
    .pp-card h3 { font: 700 18px "Poppins", sans-serif; margin: 0 0 12px; color: var(--ungu-gelap); }

    .pp-podium { display: grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 14px; align-items: end; margin: 10px 0 20px; }
    .pp-pod { background: #fff; color: var(--hitam); border-radius: 20px; text-align: center; padding: 18px 12px 0; position: relative; box-shadow: 0 8px 0 rgba(0,0,0,.2); min-width: 0; overflow: visible; }
    .pp-pod.r1 { padding-top: 30px; }
    .pp-pod .ava { margin: 0 auto 10px; }
    .pp-pod .nm { font: 700 18px/1.2 "Poppins", sans-serif; overflow-wrap: anywhere; }
    .pp-pod.r1 .nm { font-size: 22px; }
    .pp-pod .sk { font: 800 26px "Poppins", sans-serif; color: var(--ungu); margin-top: 4px; }
    .pp-pod.r1 .sk { font-size: 32px; }
    .pp-pod .mini { font-size: 13px; color: var(--abu); font-weight: 700; }
    .pp-pod .alas { margin: 12px -12px 0; border-radius: 0 0 20px 20px; font: 800 28px "Poppins", sans-serif; color: #fff; padding-top: 6px; }
    .pp-pod.r1 .alas { background: linear-gradient(180deg, #ffd166, #f2a900); height: 80px; }
    .pp-pod.r2 .alas { background: linear-gradient(180deg, #d9dde3, #a6adb8); height: 54px; }
    .pp-pod.r3 .alas { background: linear-gradient(180deg, #f0b48a, #c9783f); height: 40px; }
    .pp-pod.kosong { opacity: .35; }
    .pp-crown { position: absolute; top: -24px; left: 50%; transform: translateX(-50%); width: 52px; filter: drop-shadow(0 3px 0 rgba(0,0,0,.2)); }

    .ava { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; color: #fff; font: 800 18px "Poppins", sans-serif; flex: none; box-shadow: inset 0 -4px 0 rgba(0,0,0,.18); }
    .ava.besar { width: 76px; height: 76px; font-size: 26px; border: 4px solid #fff; box-shadow: 0 0 0 4px var(--ungu-muda), inset 0 -5px 0 rgba(0,0,0,.18); }
    .pp-pod.r1 .ava.besar { width: 92px; height: 92px; font-size: 30px; box-shadow: 0 0 0 5px #ffd166, inset 0 -6px 0 rgba(0,0,0,.18); }

    .pp-row { display: grid; grid-template-columns: 44px 44px 1fr auto; gap: 12px; align-items: center; padding: 10px 12px; border-radius: 14px; background: #faf7fe; margin-bottom: 8px; transition: transform .5s cubic-bezier(.2,.8,.2,1), background .4s; }
    .pp-row.naik { background: #e9fbf0; }
    .pp-rank { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font: 800 15px "Poppins", sans-serif; background: var(--ungu-muda); color: var(--ungu-tua); }
    .pp-row .ava { width: 42px; height: 42px; font-size: 15px; }
    .pp-nm { font-weight: 700; font-size: 17px; overflow-wrap: anywhere; }
    .pp-meta { font-size: 13px; color: var(--abu); font-weight: 600; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .pp-bar { height: 6px; border-radius: 99px; background: var(--ungu-muda); margin-top: 6px; overflow: hidden; }
    .pp-bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #a77be0, var(--ungu)); transition: width .8s ease; }
    .pp-skor { font: 800 20px "Poppins", sans-serif; color: var(--ungu-gelap); text-align: right; min-width: 70px; }
    .pp-delta { font-size: 12px; font-weight: 800; color: #15803d; font-family: "Quicksand", sans-serif; }
    .bintang { color: var(--emas); }

    .pp-join { text-align: center; }
    .pp-join img { width: 100%; max-width: 200px; border-radius: 12px; }
    .pp-kode { font: 800 40px "Poppins", sans-serif; letter-spacing: .12em; color: var(--ungu-gelap); margin: 6px 0 0; }
    .pp-url { font-size: 13px; color: var(--abu); font-weight: 700; overflow-wrap: anywhere; }
    .pp-awards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .pp-award { background: #fff; color: var(--hitam); border-radius: 20px; padding: 16px; text-align: center; box-shadow: 0 8px 0 rgba(0,0,0,.18); animation: pp-pop .5s both; }
    .pp-award svg { width: 44px; height: 44px; }
    .pp-award .jd { font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--abu); margin-top: 6px; }
    .pp-award .nm { font: 700 20px "Poppins", sans-serif; margin: 2px 0; overflow-wrap: anywhere; }
    .pp-award .dt { font-size: 13px; color: var(--abu); font-weight: 600; }
    @keyframes pp-pop { from { transform: scale(.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .pp-empty { text-align: center; padding: 30px 10px; }
    .pp-empty .besar { font: 700 26px "Poppins", sans-serif; color: var(--ungu-gelap); }
    .pp-legend { font-size: 13px; color: var(--abu); font-weight: 600; line-height: 1.6; }
    #pp-confetti { position: fixed; inset: 0; pointer-events: none; z-index: 50; }
    @media (max-width: 640px) {
        .pp-podium { gap: 8px; }
        .pp-pod .nm { font-size: 14px; } .pp-pod.r1 .nm { font-size: 16px; }
        .pp-pod .sk { font-size: 18px; } .pp-pod.r1 .sk { font-size: 22px; }
        .ava.besar { width: 54px; height: 54px; font-size: 18px; } .pp-pod.r1 .ava.besar { width: 64px; height: 64px; font-size: 22px; }
        .pp-row { grid-template-columns: 36px 38px 1fr auto; gap: 8px; }
        .pp-skor { font-size: 17px; min-width: 56px; }
        .pp-awards { grid-template-columns: 1fr 1fr; gap: 10px; }
        .pp-award { padding: 12px 8px; } .pp-award .nm { font-size: 16px; } .pp-award svg { width: 34px; height: 34px; }
    }
    @media (prefers-reduced-motion: reduce) { .pp-row, .pp-award, .pp-dot { transition: none; animation: none; } }
</style>

<canvas id="pp-confetti"></canvas>
<div class="pp-wrap">
    <div class="pp-head">
        <div class="pp-brand">
            <div class="pp-logo">KO</div>
            <div style="min-width:0">
                <p class="pp-title">{{ $session->quiz->title ?? 'Kuis' }}</p>
                <div class="pp-sub">{{ $session->modeLabel() }}{{ $session->host ? ' · ' . $session->host->name : '' }}</div>
            </div>
        </div>
        <div class="pp-chips">
            <span class="pp-chip"><span class="pp-dot" id="dot"></span><span id="status">Memuat</span></span>
            <span class="pp-chip" id="chip-soal" style="display:none"><span id="soal"></span><span class="pp-progress"><div id="progress"></div></span></span>
            <span class="pp-chip"><span id="jml">0</span> peserta</span>
        </div>
    </div>

    <div class="pp-awards" id="awards" style="display:none"></div>

    <div class="pp-grid">
        <div>
            <div class="pp-podium" id="podium"></div>
            <div class="pp-card">
                <h3 id="judul-daftar">Papan peringkat</h3>
                <div id="list"></div>
                <div class="pp-empty" id="kosong" style="display:none">
                    <div class="besar">Menunggu peserta</div>
                    <p>Scan QR di samping atau buka alamat gabung untuk ikut bermain.</p>
                </div>
                <p class="pp-legend" id="sedikit" style="display:none;margin:0">Peringkat berikutnya muncul di sini begitu peserta lebih dari tiga.</p>
            </div>
        </div>
        <div>
            <div class="pp-card pp-join" style="margin-bottom:20px">
                <h3>Ikut bermain</h3>
                <img src="{{ route('live.qr', $session->code) }}" alt="QR gabung">
                <div class="pp-kode">{{ $session->code }}</div>
                <div class="pp-url">{{ preg_replace('#^https?://#', '', route('live.enter')) }}</div>
            </div>
            <div class="pp-card pp-legend">
                <span class="bintang">&#9733;</span> = penjawab benar tercepat pada satu soal.<br>
                Jawaban benar 500 poin, ditambah bonus kecepatan dan bonus beruntun.<br>
                Papan diperbarui otomatis.
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.body.classList.add('papan');
(function () {
    const L = window.LIVE;
    const URL = @json(route('live.board.data', $session->code));
    const $ = id => document.getElementById(id);
    const WARNA = ['#8854C0', '#e2537a', '#2f9e8f', '#f08a24', '#3a7bd5', '#c0392b', '#16a085', '#7d5fff', '#d35400', '#2c3e9e'];
    const STATUS = { lobby: 'Menunggu dimulai', question: 'Soal berjalan', reveal: 'Pembahasan', open: 'Tantangan dibuka', finished: 'Selesai' };
    const DIAM = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rows = new Map();
    const shown = new Map();
    let lastRanks = new Map(), finishedOnce = false;

    function warna(nama) { let h = 0; for (const c of nama) h = (h * 31 + c.charCodeAt(0)) >>> 0; return WARNA[h % WARNA.length]; }
    function inisial(nama) { const p = nama.trim().split(/\s+/); return ((p[0] || '?')[0] + (p[1] ? p[1][0] : (p[0][1] || ''))).toUpperCase(); }
    function avatar(nama, kelas) { return '<div class="ava ' + (kelas || '') + '" style="background:' + warna(nama) + '">' + L.esc(inisial(nama)) + '</div>'; }
    function bintang(n) { return n > 0 ? '<span class="bintang">&#9733;' + (n > 1 ? n : '') + '</span>' : ''; }

    // Angka skor naik bertahap dari nilai yang terakhir tampil.
    function hitung(el, key, target) {
        const from = shown.has(key) ? shown.get(key) : 0;
        shown.set(key, target);
        if (from === target || DIAM) { el.textContent = target; return; }
        const t0 = performance.now(), dur = 900;
        (function step(t) {
            const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
            el.textContent = Math.round(from + (target - from) * e);
            if (k < 1) requestAnimationFrame(step);
        })(t0);
    }

    const CROWN = '<svg class="pp-crown" viewBox="0 0 64 40" aria-hidden="true"><path d="M4 34 L8 8 L22 22 L32 4 L42 22 L56 8 L60 34 Z" fill="#ffd166" stroke="#e0a100" stroke-width="3" stroke-linejoin="round"/><circle cx="8" cy="8" r="4" fill="#fff4cc"/><circle cx="32" cy="4" r="4" fill="#fff4cc"/><circle cx="56" cy="8" r="4" fill="#fff4cc"/></svg>';

    function podium(top) {
        const slot = (p, r) => p
            ? '<div class="pp-pod r' + r + '">' + (r === 1 ? CROWN : '') + avatar(p.nickname, 'besar') +
              '<div class="nm">' + L.esc(p.nickname) + ' ' + bintang(p.stars) + '</div>' +
              '<div class="sk" data-pod="' + r + '">' + (shown.get('pod' + r + ':' + p.id) ?? 0) + '</div>' +
              '<div class="mini">' + p.correct + ' benar</div><div class="alas">' + r + '</div></div>'
            : '<div class="pp-pod r' + r + ' kosong"><div class="ava besar" style="background:#d9c8ef">?</div><div class="nm">-</div><div class="sk">&nbsp;</div><div class="mini">&nbsp;</div><div class="alas">' + r + '</div></div>';
        $('podium').innerHTML = slot(top[1], 2) + slot(top[0], 1) + slot(top[2], 3);
        top.forEach((p, i) => {
            const el = $('podium').querySelector('[data-pod="' + (i + 1) + '"]');
            if (el) hitung(el, 'pod' + (i + 1) + ':' + p.id, p.score);
        });
    }

    function daftar(list, max) {
        const box = $('list');
        const before = new Map();
        rows.forEach((el, id) => before.set(id, el.getBoundingClientRect().top));

        const ids = new Set(list.map(p => p.id));
        rows.forEach((el, id) => { if (!ids.has(id)) { el.remove(); rows.delete(id); } });

        list.forEach(p => {
            let el = rows.get(p.id);
            if (!el) {
                el = document.createElement('div');
                el.className = 'pp-row';
                el.innerHTML = '<div class="pp-rank"></div>' + avatar(p.nickname) +
                    '<div style="min-width:0"><div class="pp-nm"></div><div class="pp-meta"></div><div class="pp-bar"><div></div></div></div>' +
                    '<div class="pp-skor"><div class="angka">0</div><div class="pp-delta"></div></div>';
                rows.set(p.id, el);
            }
            const prev = lastRanks.get(p.id);
            el.querySelector('.pp-rank').textContent = p.rank;
            el.querySelector('.pp-nm').innerHTML = L.esc(p.nickname) + ' ' + bintang(p.stars);
            el.querySelector('.pp-meta').innerHTML = '<span>' + p.correct + ' benar</span>' + (p.best_streak >= 2 ? '<span>beruntun ' + p.best_streak + '</span>' : '');
            el.querySelector('.pp-bar > div').style.width = (max > 0 ? Math.max(3, 100 * p.score / max) : 3) + '%';
            const naik = prev !== undefined && p.rank < prev;
            el.querySelector('.pp-delta').textContent = naik ? 'naik ' + (prev - p.rank) : '';
            el.classList.toggle('naik', naik);
            hitung(el.querySelector('.angka'), 'r' + p.id, p.score);
            box.appendChild(el); // urutan DOM mengikuti peringkat baru
        });

        // FLIP: baris bergeser halus dari posisi lama ke posisi barunya.
        if (!DIAM) {
            rows.forEach((el, id) => {
                const old = before.get(id);
                if (old === undefined) return;
                const dy = old - el.getBoundingClientRect().top;
                if (!dy) return;
                el.style.transition = 'none';
                el.style.transform = 'translateY(' + dy + 'px)';
                requestAnimationFrame(() => requestAnimationFrame(() => { el.style.transition = ''; el.style.transform = ''; }));
            });
        }
    }

    const IKON = {
        juara: '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M14 6h20v8a10 10 0 0 1-20 0z" fill="#ffd166" stroke="#e0a100" stroke-width="2.5"/><path d="M14 9H7a6 6 0 0 0 7 8M34 9h7a6 6 0 0 1-7 8" fill="none" stroke="#e0a100" stroke-width="2.5"/><rect x="21" y="24" width="6" height="8" fill="#e0a100"/><rect x="14" y="32" width="20" height="7" rx="2" fill="#8854C0"/></svg>',
        tercepat: '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M27 4 9 27h12l-3 17 21-25H27z" fill="#ffd166" stroke="#e0a100" stroke-width="2.5" stroke-linejoin="round"/></svg>',
        beruntun: '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 4c3 8 12 12 12 23a12 12 0 0 1-24 0c0-6 3-9 5-12 1 4 3 6 5 6-1-6 0-12 2-17z" fill="#f27a24" stroke="#c4531a" stroke-width="2.5" stroke-linejoin="round"/><path d="M24 26c2 3 6 5 6 9a6 6 0 0 1-12 0c0-3 3-5 6-9z" fill="#ffd166"/></svg>',
        teliti: '<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="19" fill="#fff" stroke="#8854C0" stroke-width="3"/><circle cx="24" cy="24" r="12" fill="#efe6fa" stroke="#8854C0" stroke-width="3"/><circle cx="24" cy="24" r="5" fill="#8854C0"/></svg>',
    };
    let awardsKey = '';
    function penghargaan(awards) {
        const key = JSON.stringify(awards);
        if (key === awardsKey) return; // jangan ulang animasi tiap pembaruan
        awardsKey = key;
        const box = $('awards');
        box.style.display = awards.length ? '' : 'none';
        box.innerHTML = awards.map((a, i) => '<div class="pp-award" style="animation-delay:' + (i * 0.12) + 's">' +
            (IKON[a.key] || IKON.juara) + '<div class="jd">' + L.esc(a.title) + '</div><div class="nm">' +
            L.esc(a.nickname) + '</div><div class="dt">' + L.esc(a.detail) + '</div></div>').join('');
    }

    function konfeti() {
        if (DIAM) return;
        const c = $('pp-confetti'), x = c.getContext('2d');
        c.width = innerWidth; c.height = innerHeight;
        const warna = ['#ffd166', '#8854C0', '#e2537a', '#3ddc84', '#3a7bd5', '#ffffff'];
        const ps = Array.from({ length: 160 }, () => ({
            x: Math.random() * c.width, y: -20 - Math.random() * c.height * 0.5, w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
            vy: 2 + Math.random() * 3, vx: -1.5 + Math.random() * 3, r: Math.random() * 6, vr: -0.2 + Math.random() * 0.4,
            c: warna[Math.floor(Math.random() * warna.length)],
        }));
        const t0 = performance.now();
        (function frame(t) {
            x.clearRect(0, 0, c.width, c.height);
            ps.forEach(p => {
                p.x += p.vx; p.y += p.vy; p.r += p.vr;
                x.save(); x.translate(p.x, p.y); x.rotate(p.r); x.fillStyle = p.c; x.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); x.restore();
            });
            if (t - t0 < 5000) requestAnimationFrame(frame); else x.clearRect(0, 0, c.width, c.height);
        })(t0);
    }

    async function poll() {
        let selesai = false;
        try {
            const d = await L.getJson(URL);
            selesai = d.status === 'finished';
            $('status').textContent = STATUS[d.status] || d.status;
            $('dot').classList.toggle('mati', selesai);
            $('jml').textContent = d.players;
            const tampilSoal = d.mode === 'live' && d.index >= 0;
            $('chip-soal').style.display = tampilSoal ? '' : 'none';
            if (tampilSoal) {
                const n = selesai ? d.total : d.index + 1;
                $('soal').textContent = 'Soal ' + n + '/' + d.total;
                $('progress').style.width = (100 * n / d.total) + '%';
            }

            const lb = d.leaderboard;
            $('kosong').style.display = lb.length ? 'none' : '';
            $('podium').style.display = lb.length ? '' : 'none';
            $('sedikit').style.display = lb.length && lb.length <= 3 ? '' : 'none';
            if (lb.length) podium(lb.slice(0, 3));
            $('judul-daftar').textContent = lb.length > 3 ? 'Peringkat berikutnya' : 'Papan peringkat';
            daftar(lb.slice(3), lb[0] ? lb[0].score : 0);
            lastRanks = new Map(lb.map(p => [p.id, p.rank]));
            // Juara 1-3 sudah tampil di podium; kartu cukup penghargaan khusus.
            penghargaan((d.awards || []).filter(a => !a.key.startsWith('juara')));

            if (selesai && !finishedOnce && lb.length) { finishedOnce = true; konfeti(); }
        } catch (e) {}
        if (!selesai) setTimeout(poll, 3000);
    }
    poll();
})();
</script>
@endsection
