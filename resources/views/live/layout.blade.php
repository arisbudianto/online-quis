<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <title>@yield('title', 'Kuis Interaktif') · Kuis Online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    {{-- Halaman mandiri (tanpa Vite/Bootstrap) supaya ringan di HP dan proyektor. --}}
    <style>
        :root {
            --hitam: #2a1f33; --abu: #685972; --garis: #ece6f3; --latar: #f6f2fb; --merah: #8854C0;
            --ungu: #8854C0; --ungu-tua: #6a3ea1; --ungu-gelap: #2b1055; --ungu-muda: #efe6fa; --emas: #f2a900;
            --opsi-0: #D7263D; --opsi-1: #1B6AC9; --opsi-2: #C98A00; --opsi-3: #23883F;
            --opsi-4: #7B3FB5; --opsi-5: #0F8B8D;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: "Quicksand", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-weight: 500; color: var(--hitam); background: var(--latar); -webkit-tap-highlight-color: transparent; }
        .bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid var(--garis); font-weight: 700; color: var(--ungu-gelap); }
        .bar small { font-weight: 600; color: var(--abu); }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        .wrap.lebar { max-width: 1200px; }
        .kartu { background: #fff; border: 1px solid var(--garis); padding: 20px; margin-bottom: 16px; border-radius: 16px; box-shadow: 0 4px 0 rgba(42, 31, 51, .06); }
        h1, h2, .status-besar, .kode-besar, .soal, .bar { font-family: "Poppins", system-ui, sans-serif; }
        h1 { font-size: 28px; line-height: 1.2; margin: 0 0 8px; font-weight: 700; }
        h2 { font-size: 22px; margin: 0 0 8px; font-weight: 700; }
        p { color: var(--abu); line-height: 1.5; margin: 0 0 12px; }
        .kecil { font-size: 13px; color: var(--abu); }
        .tengah { text-align: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; background: var(--ungu); color: #fff; padding: 14px 20px; font-size: 16px; font-weight: 700; font-family: "Quicksand", sans-serif; cursor: pointer; text-decoration: none; border-radius: 10px; box-shadow: 0 4px 0 var(--ungu-tua); transition: transform .08s, box-shadow .08s; }
        .btn:hover { background: #9563cc; }
        .btn:active { transform: translateY(3px); box-shadow: 0 1px 0 var(--ungu-tua); }
        .btn.putih { background: #fff; color: var(--ungu-tua); border: 2px solid #d9c8ef; box-shadow: 0 4px 0 #d9c8ef; }
        .btn.putih:hover { background: #faf6ff; }
        .kode-besar { color: var(--ungu-gelap); }
        .btn.penuh { width: 100%; }
        .btn:disabled { opacity: .5; cursor: default; }
        input[type=text] { width: 100%; padding: 14px; font-size: 18px; border: 2px solid #d9c8ef; border-radius: 10px; font-family: "Quicksand", sans-serif; font-weight: 700; }
        input[type=text]:focus { outline: none; border-color: var(--ungu); }
        .galat { color: #b91c1c; font-size: 14px; margin-top: 8px; }
        .opsi-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
        @media (min-width: 640px) { .opsi-grid { grid-template-columns: 1fr 1fr; } }
        .opsi { display: flex; align-items: center; gap: 12px; min-height: 64px; padding: 12px 16px; color: #fff; font-size: 17px; font-weight: 700; border: 0; text-align: left; cursor: pointer; width: 100%; border-radius: 12px; box-shadow: inset 0 -5px 0 rgba(0,0,0,.18); font-family: "Quicksand", sans-serif; transition: transform .08s; }
        .opsi:active { transform: translateY(2px); }
        .opsi .huruf { flex: 0 0 34px; height: 34px; display: grid; place-items: center; background: rgba(255,255,255,.25); font-weight: 800; border-radius: 8px; }
        .opsi.redup { opacity: .35; }
        .opsi.benar { outline: 4px solid #111; }
        .o0 { background: var(--opsi-0); } .o1 { background: var(--opsi-1); } .o2 { background: var(--opsi-2); }
        .o3 { background: var(--opsi-3); } .o4 { background: var(--opsi-4); } .o5 { background: var(--opsi-5); }
        .soal { font-size: 21px; font-weight: 700; line-height: 1.35; margin: 0 0 14px; color: var(--hitam); white-space: pre-line; }
        .soal-img { max-width: 100%; max-height: 260px; display: block; margin: 0 auto 14px; }
        .timer { font-size: 15px; font-weight: 800; }
        .timer-bar { height: 10px; background: var(--ungu-muda); margin: 8px 0 14px; border-radius: 99px; overflow: hidden; }
        .timer-bar > div { height: 100%; background: var(--merah); transition: width .25s linear; }
        .status-besar { font-size: 26px; font-weight: 800; margin: 8px 0; }
        .hijau { color: #15803d; } .merahteks { color: #b91c1c; }
        table.papan { width: 100%; border-collapse: collapse; background: #fff; }
        table.papan td, table.papan th { padding: 10px 12px; border-bottom: 1px solid var(--garis); text-align: left; }
        table.papan th { font-size: 13px; color: var(--abu); }
        table.papan td.angka, table.papan th.angka { text-align: right; }
        tr.saya td { background: #fff7e6; font-weight: 800; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip { background: #fff; border: 1px solid var(--garis); padding: 8px 12px; font-weight: 700; display: inline-flex; gap: 8px; align-items: center; border-radius: 99px; }
        .chip button { border: 0; background: none; color: var(--abu); cursor: pointer; font-size: 13px; padding: 0; }
        .hidden { display: none !important; }
        .kode-besar { font-size: 56px; font-weight: 900; letter-spacing: .12em; }
        .baris { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-start; }
        .baris > * { flex: 1 1 280px; }
        .podium { display: flex; justify-content: center; align-items: flex-end; gap: 12px; margin: 20px 0; }
        .podium > div { background: #fff; border: 1px solid var(--garis); padding: 12px; width: 32%; text-align: center; font-weight: 800; border-radius: 16px; }
        .podium .p1 { min-height: 170px; border-top: 6px solid #C98A00; }
        .podium .p2 { min-height: 130px; border-top: 6px solid #8a8f98; }
        .podium .p3 { min-height: 100px; border-top: 6px solid #a0522d; }
        .batang { height: 14px; background: var(--ungu-muda); margin-top: 6px; border-radius: 99px; overflow: hidden; }
        .bintang { color: #C98A00; font-weight: 900; white-space: nowrap; }
        .pemimpin { border-left: 6px solid #C98A00; }
        .pemimpin .nama { font-size: 26px; font-weight: 900; }
        .chip.cepat { border-color: #C98A00; background: #fff7e6; }
        .penghargaan { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 16px 0; text-align: center; }
        .penghargaan > div { background: #fff; border: 1px solid var(--garis); border-top: 6px solid var(--emas); padding: 14px; border-radius: 16px; }
        .penghargaan .judul { font-size: 13px; font-weight: 800; color: var(--abu); text-transform: uppercase; letter-spacing: .04em; }
        .penghargaan .nama { font-size: 22px; font-weight: 900; margin: 6px 0; }
        .batang > div { height: 100%; }
    </style>
</head>
<body>
    @yield('body')
    <script>
        window.LIVE = {
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            offset: 0,
            async syncClock(url) {
                try {
                    const t0 = Date.now();
                    const r = await fetch(url, { cache: 'no-store' });
                    const j = await r.json();
                    const t1 = Date.now();
                    this.offset = j.now_ms + (t1 - t0) / 2 - t1;
                } catch (e) { this.offset = 0; }
            },
            now() { return Date.now() + this.offset; },
            async getJson(url) {
                const r = await fetch(url + (url.includes('?') ? '&' : '?') + 't=' + Date.now(), { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                if (!r.ok) { const e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
                return r.json();
            },
            async post(url, data) {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(data || {}),
                });
                let j = {};
                try { j = await r.json(); } catch (e) {}
                if (!r.ok) {
                    const msg = (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || j.message || ('HTTP ' + r.status);
                    const e = new Error(msg); e.status = r.status; throw e;
                }
                return j;
            },
            esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; },
            letter(i) { return 'ABCDEF'[i] || '?'; },
            stars(n) { return n > 0 ? ' <span class="bintang" title="' + n + ' bintang tercepat">&#9733;' + (n > 1 ? n : '') + '</span>' : ''; },
            boardRows(list, meId) {
                return list.map(p => '<tr class="' + (p.id === meId ? 'saya' : '') + '"><td>' + p.rank + '</td><td>' + this.esc(p.nickname) +
                    this.stars(p.stars) + '</td><td class="angka">' + p.score + '</td></tr>').join('');
            },
            awardCards(awards) {
                return (awards || []).map(a => '<div><div class="judul">' + this.esc(a.title) + '</div><div class="nama">' +
                    this.esc(a.nickname) + '</div><div class="kecil">' + this.esc(a.detail) + '</div></div>').join('');
            },
        };
    </script>
    @yield('scripts')
</body>
</html>
