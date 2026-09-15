<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kuis Online') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- PWA -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#DC3B1C">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Kuis Online">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#111111] antialiased bg-white">
        <div class="flex min-h-screen flex-col">

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

                <p class="max-w-md text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-right">
                    Program Studi Teknik Informatika dan Komputer FKIP &middot; Universitas Sebelas Maret
                </p>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="flex flex-col gap-1 border-t border-gray-200 bg-[#F4F4F2] px-6 py-5 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:px-10">
                <span>Kuis Online &middot; Program Studi Teknik Informatika dan Komputer, FKIP UNS</span>
                <span>&copy; {{ date('Y') }}</span>
            </footer>
        </div>

        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
            }
        </script>
    </body>
</html>
