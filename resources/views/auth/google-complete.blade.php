<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Daftar dengan Google</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Lengkapi data kampus
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            Akun Google <strong>{{ $pending['email'] }}</strong> sudah terverifikasi.
            Isi NIM dan kelas untuk membuat akun mahasiswa.
        </p>

        <hr class="my-6 border-gray-200">

        <form method="POST" action="{{ route('google.complete.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="text-xs font-bold uppercase tracking-wide text-gray-700">Nama Lengkap</label>
                <x-text-input id="name" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="text" name="name" :value="old('name', $pending['name'])" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="nim" class="text-xs font-bold uppercase tracking-wide text-gray-700">NIM</label>
                    <x-text-input id="nim" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="text" name="nim" :value="old('nim')" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('nim')" class="mt-2" />
                </div>

                <div>
                    <label for="kelas" class="text-xs font-bold uppercase tracking-wide text-gray-700">Kelas</label>
                    <select id="kelas" name="kelas" required class="block mt-1.5 w-full rounded-none border-gray-300 py-2 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]">
                        <option value="" disabled {{ old('kelas') ? '' : 'selected' }}>Pilih kelas</option>
                        @foreach (\App\Models\User::KELAS as $opt)
                            <option value="{{ $opt }}" {{ old('kelas') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('kelas')" class="mt-2" />
                </div>
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                <span>Buat akun</span>
                <span aria-hidden="true">&rarr;</span>
            </button>

            <hr class="my-6 border-gray-200">

            <p class="text-sm text-gray-600">
                Bukan akun Google yang benar?
                <a class="font-semibold text-[#111111] underline" href="{{ route('google.redirect') }}">Pilih akun Google lain</a>
            </p>
        </form>
    </div>
</x-guest-layout>
