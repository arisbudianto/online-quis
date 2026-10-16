<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Daftar</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Buat akun
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            Daftar sebagai mahasiswa/siswa untuk mengerjakan kuis dari dosen/guru Anda, atau sebagai dosen/guru untuk membuat kuis.
        </p>

        <hr class="my-6 border-gray-200">

        @if (\App\Support\GoogleOAuth::enabled())
            <x-google-button label="Daftar dengan Google" />
            <x-input-error :messages="$errors->get('google')" class="mt-2" />
            <p class="mt-2 text-sm text-gray-600">Email langsung terverifikasi. Anda tinggal memilih peran dan melengkapi data.</p>
            <p class="mt-5 mb-4 text-center text-xs font-bold uppercase tracking-wide text-gray-500">atau isi formulir</p>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="text-xs font-bold uppercase tracking-wide text-gray-700">Nama Lengkap</label>
                <x-text-input id="name" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            @include('auth.partials.role-fields')

            <div>
                <label for="email" class="text-xs font-bold uppercase tracking-wide text-gray-700">Email</label>
                <x-text-input id="email" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="name@student.uns.ac.id" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="text-xs font-bold uppercase tracking-wide text-gray-700">Password</label>
                <x-text-input id="password" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="text-xs font-bold uppercase tracking-wide text-gray-700">Konfirmasi Password</label>
                <x-text-input id="password_confirmation" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                <span>Daftar</span>
                <span aria-hidden="true">&rarr;</span>
            </button>

            <hr class="my-6 border-gray-200">

            <p class="text-sm text-gray-600">
                Already have an account?
                <a class="font-semibold text-[#111111] underline" href="{{ route('login') }}">Sign in here</a>
            </p>
        </form>
    </div>
</x-guest-layout>
