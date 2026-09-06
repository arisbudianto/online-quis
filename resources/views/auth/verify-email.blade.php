<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Satu langkah lagi</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Verifikasi email Anda
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            Terima kasih sudah mendaftar. Sebelum mulai, klik link verifikasi yang baru saja
            kami kirim ke email Anda. Belum menerima emailnya? Kami akan kirim ulang.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mt-4 rounded-none border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                Link verifikasi baru sudah dikirim ke email yang Anda daftarkan.
            </div>
        @endif

        <hr class="my-6 border-gray-200">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                    <span>Resend Verification Email</span>
                    <span aria-hidden="true">&rarr;</span>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-gray-600 underline hover:text-[#111111]">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
