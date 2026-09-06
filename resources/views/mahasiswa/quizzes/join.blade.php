@extends('layouts.app')
@section('title', 'Masukkan Kode Kuis')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Masukkan Kode Kuis</h4>
                <p class="text-muted">Minta kode kuis (6 karakter) dari dosen Anda, atau scan QR code yang ditampilkan.</p>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <button type="button" id="scan-toggle-btn" class="btn btn-outline-primary w-100">
                            📷 Scan Kamera
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" id="upload-toggle-btn" class="btn btn-outline-primary w-100">
                            🖼 Upload Gambar QR
                        </button>
                    </div>
                </div>

                <div id="qr-reader-wrapper" style="display: none;" class="mb-3">
                    <div id="qr-reader" style="width: 100%;"></div>
                    <button type="button" id="scan-cancel-btn" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                        Batalkan Scan
                    </button>
                    <div id="qr-error" class="text-danger small mt-2" style="display: none;"></div>
                </div>

                <div id="qr-upload-wrapper" style="display: none;" class="mb-3">
                    <input type="file" id="qr-file-input" accept="image/*" class="form-control">
                    <div id="qr-upload-status" class="text-muted small mt-2"></div>
                    <div id="qr-upload-error" class="text-danger small mt-2" style="display: none;"></div>
                </div>

                <form id="join-form" method="POST" action="{{ route('mahasiswa.quizzes.join.submit') }}">
                    @csrf
                    <input
                        type="text"
                        id="code-input"
                        name="code"
                        class="form-control form-control-lg text-center mb-3"
                        style="letter-spacing: 4px; text-transform: uppercase;"
                        maxlength="8"
                        placeholder="Contoh: A7K9QZ"
                        value="{{ old('code', $prefillCode ?? '') }}"
                        autofocus
                        required
                    >
                    <button class="btn btn-primary w-100">Cari Kuis</button>
                </form>

                <hr>
                <a href="{{ route('mahasiswa.quizzes.index') }}" class="text-muted">← Lihat daftar kuis publik</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    const scanToggleBtn = document.getElementById('scan-toggle-btn');
    const scanCancelBtn = document.getElementById('scan-cancel-btn');
    const readerWrapper = document.getElementById('qr-reader-wrapper');
    const errorBox = document.getElementById('qr-error');
    const codeInput = document.getElementById('code-input');
    const joinForm = document.getElementById('join-form');

    const uploadToggleBtn = document.getElementById('upload-toggle-btn');
    const uploadWrapper = document.getElementById('qr-upload-wrapper');
    const fileInput = document.getElementById('qr-file-input');
    const uploadStatus = document.getElementById('qr-upload-status');
    const uploadError = document.getElementById('qr-upload-error');

    let html5QrCode = null;
    let isScanning = false;

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.style.display = 'block';
    }

    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().catch(() => {});
        }
        isScanning = false;
        readerWrapper.style.display = 'none';
        scanToggleBtn.style.display = 'block';
    }

    function extractCode(decodedText) {
        // QR meng-encode URL lengkap (https://.../kuis/gabung?code=XXXXXX).
        // Ambil parameter "code"-nya. Kalau ternyata isinya bukan URL
        // (misal QR generik), coba pakai teksnya langsung sebagai kode.
        try {
            const url = new URL(decodedText);
            const code = url.searchParams.get('code');
            if (code) return code;
        } catch (e) {
            // bukan URL valid, lanjut ke fallback di bawah
        }
        return decodedText.trim();
    }

    scanToggleBtn.addEventListener('click', function () {
        uploadWrapper.style.display = 'none';
        errorBox.style.display = 'none';
        readerWrapper.style.display = 'block';
        scanToggleBtn.style.display = 'none';

        html5QrCode = new Html5Qrcode('qr-reader');
        isScanning = true;

        html5QrCode.start(
            { facingMode: 'environment' }, // kamera belakang HP
            { fps: 10, qrbox: 250 },
            function onScanSuccess(decodedText) {
                stopScanner();
                const code = extractCode(decodedText);
                codeInput.value = code.toUpperCase();
                joinForm.submit(); // langsung cari kuis otomatis setelah scan berhasil
            },
            function onScanFailure() {
                // dipanggil terus-menerus tiap frame yang gagal terbaca -- normal, abaikan.
            }
        ).catch(function (err) {
            stopScanner();
            showError('Tidak bisa mengakses kamera. Pastikan Anda mengizinkan akses kamera di browser, atau gunakan app Kamera bawaan HP untuk scan QR sebagai alternatif.');
        });
    });

    scanCancelBtn.addEventListener('click', stopScanner);

    // ---- Upload file gambar QR (misal file yang sudah didownload dari halaman Kelola Kuis) ----
    uploadToggleBtn.addEventListener('click', function () {
        stopScanner();
        uploadError.style.display = 'none';
        uploadStatus.textContent = '';
        uploadWrapper.style.display = uploadWrapper.style.display === 'none' ? 'block' : 'none';
    });

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        uploadError.style.display = 'none';
        uploadStatus.textContent = 'Membaca gambar...';

        // html5-qrcode butuh elemen div target walau cuma dipakai untuk baca file --
        // buat elemen tersembunyi sekali pakai kalau belum ada (SEBELUM instance dibuat).
        if (!document.getElementById('qr-reader-file-temp')) {
            const hiddenDiv = document.createElement('div');
            hiddenDiv.id = 'qr-reader-file-temp';
            hiddenDiv.style.display = 'none';
            document.body.appendChild(hiddenDiv);
        }

        const fileScanner = new Html5Qrcode('qr-reader-file-temp', /* verbose= */ false);

        fileScanner.scanFile(file, false)
            .then(function (decodedText) {
                uploadStatus.textContent = 'Kode ditemukan, mencari kuis...';
                const code = extractCode(decodedText);
                codeInput.value = code.toUpperCase();
                joinForm.submit();
            })
            .catch(function (err) {
                uploadStatus.textContent = '';
                uploadError.textContent = 'Gagal membaca QR dari gambar ini. Pastikan file berisi QR code yang jelas (tidak buram/terpotong), lalu coba lagi.';
                uploadError.style.display = 'block';
            });
    });
</script>
@endsection
