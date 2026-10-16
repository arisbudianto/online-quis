<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        @if ($state === 'sent')
            <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Reset password</p>
            <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">Cek email Anda</h1>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                Link untuk membuat password baru sudah dikirim ke <strong>{{ $email }}</strong>.
                Link berlaku 60 menit. Kalau belum masuk dalam beberapa menit, cek folder Spam atau Promosi.
            </p>
        @elseif ($state === 'unknown')
            <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Reset password</p>
            <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">Email belum terdaftar</h1>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                Tidak ada akun Kuis Online dengan email <strong>{{ $email }}</strong>.
                Mungkin Anda mendaftar dengan email lain, atau belum punya akun.
            </p>
        @else
            <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Reset password</p>
            <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">Email tidak bisa dikirim</h1>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                Akun dengan email <strong>{{ $email }}</strong> ada, tetapi server email menolak alamat itu,
                jadi link reset tidak bisa dikirim. Biasanya karena kotak surat alamat tersebut sudah tidak aktif.
            </p>
        @endif

        <hr class="my-6 border-gray-200">

        <div class="space-y-5">
            @if ($state !== 'sent' && $google)
                <div>
                    <x-google-button label="Masuk dengan Google" />
                    <p class="mt-2 text-sm text-gray-600">
                        @if ($state === 'unknown')
                            Belum punya akun? Akun bisa langsung dibuat lewat Google.
                        @else
                            Bisa dipakai kalau {{ $email }} adalah akun Google. Tidak perlu password.
                        @endif
                    </p>
                </div>
            @endif

            @if ($state === 'undeliverable')
                <p class="text-sm text-gray-600">
                    <strong>Mahasiswa/siswa:</strong> minta dosen/guru Anda membuat password baru lewat menu Mahasiswa, tombol Password,
                    atau mengganti email akun Anda ke alamat yang aktif.<br>
                    <strong>Dosen/guru:</strong> hubungi admin Kuis Online.
                </p>
            @endif

            @if ($state === 'unknown')
                <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                    <span>Daftar akun baru</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            @endif

            <hr class="my-6 border-gray-200">

            <p class="text-sm text-gray-600">
                @if ($state === 'sent')
                    Salah alamat?
                @else
                    Mau mencoba email lain?
                @endif
                <a class="font-semibold text-[#111111] underline" href="{{ route('password.request') }}">Ulangi lupa password</a>
                atau <a class="font-semibold text-[#111111] underline" href="{{ route('login') }}">kembali ke sign in</a>.
            </p>
        </div>
    </div>
</x-guest-layout>
