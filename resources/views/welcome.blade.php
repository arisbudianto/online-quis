<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Kuis Online') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#111111] antialiased bg-white">

        <header class="flex flex-col gap-4 border-b border-gray-200 bg-[#F4F4F2] px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <a href="{{ route('welcome') }}" class="inline-flex items-center gap-3">
                <span class="grid grid-cols-2 grid-rows-2 gap-0.5">
                    <span class="h-2.5 w-2.5 bg-[#DC3B1C]"></span>
                    <span class="h-2.5 w-2.5 border border-black"></span>
                    <span class="h-2.5 w-2.5 bg-black"></span>
                    <span class="h-2.5 w-2.5 border border-black"></span>
                </span>
                <span class="text-lg font-extrabold uppercase tracking-tight">Kuis Online</span>
            </a>

            <div class="flex items-center gap-6">
                <p class="hidden max-w-md text-xs font-semibold uppercase tracking-wide text-gray-500 sm:block sm:text-right">
                    Program Studi Teknik Informatika dan Komputer FKIP &middot; Universitas Sebelas Maret
                </p>
                <a href="{{ route('login') }}" class="shrink-0 text-sm font-bold underline">Sign in</a>
            </div>
        </header>

        <section class="grid lg:grid-cols-2">
            <div class="flex items-center bg-white px-6 py-16 sm:px-10 sm:py-24">
                <div class="max-w-lg">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Lecturers &amp; Students</p>
                    <h1 class="mt-3 text-4xl font-extrabold leading-tight text-[#111111] sm:text-5xl">
                        Ujian pilihan ganda, dibagikan lewat satu kode.
                    </h1>
                    <p class="mt-5 text-base leading-relaxed text-gray-600">
                        Dosen membuat kuis dan membagikan kode 6 karakter. Mahasiswa masuk,
                        masukkan kode, dan langsung mengerjakan — soal serta jawaban diacak
                        otomatis untuk tiap mahasiswa.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-none bg-black px-6 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                            Sign in <span aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-none border border-black px-6 py-3 text-sm font-bold text-[#111111] transition hover:border-[#DC3B1C] hover:text-[#DC3B1C]">
                            Register
                        </a>
                    </div>
                </div>
            </div>

            <div class="flex flex-col justify-center gap-6 bg-[#DC3B1C] px-6 py-16 text-white sm:px-10 sm:py-24">
                <p class="text-xs font-bold uppercase tracking-wide text-white/70">How it works</p>

                <video
                    class="w-full border border-white/20"
                    src="{{ asset('videos/how-it-works.mp4') }}"
                    controls
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="metadata"
                >
                    Browser Anda tidak mendukung pemutaran video.
                </video>
            </div>
        </section>

        <footer class="flex flex-col gap-1 border-t border-gray-200 bg-[#F4F4F2] px-6 py-5 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <span>Kuis Online &middot; Program Studi Teknik Informatika dan Komputer, FKIP UNS</span>
            <span>&copy; {{ date('Y') }}</span>
        </footer>
    </body>
</html>
