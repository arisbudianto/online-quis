// Service worker MINIMAL -- cukup untuk syarat "installable" PWA.
// SENGAJA TIDAK melakukan caching agresif/offline-first untuk halaman kuis:
// mengerjakan kuis butuh koneksi real-time ke server (auto-save jawaban,
// timer, deteksi tab-switch, device-binding). Caching itu offline malah
// berbahaya/membingungkan (jawaban terlihat "tersimpan" padahal tidak).
//
// Yang di-cache cuma app shell paling dasar (manifest, ikon) + fallback
// halaman "offline.html" kalau benar-benar tidak ada koneksi sama sekali.

const CACHE_NAME = 'kuis-online-shell-v1';
const SHELL_ASSETS = [
    '/manifest.json',
    '/offline.html',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Network-first untuk semua request navigasi (buka halaman) -- kalau gagal
// (offline total), baru tampilkan offline.html. Untuk request lain (API,
// AJAX auto-save, dll) dibiarkan lewat apa adanya, TIDAK di-intercept sama
// sekali -- supaya auto-save jawaban & fitur real-time lain tidak terganggu.
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/offline.html'))
        );
    }
});
