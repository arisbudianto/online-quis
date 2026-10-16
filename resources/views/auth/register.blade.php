<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Students</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Create your account
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            Register with your campus details to start taking quizzes from your lecturers.
        </p>

        <hr class="my-6 border-gray-200">

        @if (\App\Support\GoogleOAuth::enabled())
            <x-google-button label="Daftar dengan Google" />
            <x-input-error :messages="$errors->get('google')" class="mt-2" />
            <p class="mt-2 text-sm text-gray-600">Email langsung terverifikasi. Anda tinggal mengisi NIM dan kelas.</p>
            <p class="mt-5 mb-4 text-center text-xs font-bold uppercase tracking-wide text-gray-500">atau isi formulir</p>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="text-xs font-bold uppercase tracking-wide text-gray-700">Nama Lengkap</label>
                <x-text-input id="name" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
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
                <span>Register</span>
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
