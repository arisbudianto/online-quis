<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Verifikasi</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Konfirmasi password
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            Ini area aman aplikasi. Konfirmasi password Anda sebelum melanjutkan.
        </p>

        <hr class="my-6 border-gray-200">

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <label for="password" class="text-xs font-bold uppercase tracking-wide text-gray-700">Password</label>
                <x-text-input id="password" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="password" name="password" required autocomplete="current-password" autofocus />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                <span>Confirm</span>
                <span aria-hidden="true">&rarr;</span>
            </button>
        </form>
    </div>
</x-guest-layout>
