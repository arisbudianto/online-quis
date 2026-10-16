<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · Kuis Online</title>
    {{-- Halaman error berdiri sendiri: tanpa Vite, database, atau sesi,
         supaya tetap tampil walau bagian lain aplikasi sedang bermasalah. --}}
    <style>
        :root { --merah: #DC3B1C; --hitam: #111111; --abu: #4b5563; --garis: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--hitam); background: #fff; }
        header { background: #F4F4F2; border-bottom: 1px solid var(--garis); padding: 20px 24px; font-weight: 800; letter-spacing: .02em; }
        header a { color: var(--hitam); text-decoration: none; }
        main { max-width: 460px; margin: 0 auto; padding: 64px 24px; }
        .kode { color: var(--merah); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        h1 { font-size: 32px; line-height: 1.15; margin: 8px 0 12px; }
        p { color: var(--abu); font-size: 15px; line-height: 1.6; margin: 0 0 12px; }
        hr { border: 0; border-top: 1px solid var(--garis); margin: 24px 0; }
        .tombol { display: flex; justify-content: space-between; background: #000; color: #fff; padding: 12px 20px; font-size: 14px; font-weight: 700; text-decoration: none; margin-bottom: 10px; }
        .tombol:hover { background: var(--merah); }
        .tombol.putih { background: #fff; color: var(--hitam); border: 1px solid #000; }
        .tombol.putih:hover { border-color: var(--merah); color: var(--merah); }
    </style>
</head>
<body>
    <header><a href="{{ url('/') }}">KUIS ONLINE</a></header>
    <main>
        <div class="kode">Kode @yield('code')</div>
        <h1>@yield('title')</h1>
        @yield('body')
        <hr>
        @yield('actions')
        <a class="tombol" href="{{ url('/dashboard') }}"><span>Ke halaman utama</span><span aria-hidden="true">&rarr;</span></a>
        <a class="tombol putih" href="{{ url('/login') }}"><span>Sign in</span><span aria-hidden="true">&rarr;</span></a>
    </main>
</body>
</html>
