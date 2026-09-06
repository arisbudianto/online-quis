<x-guest-layout>
    <div class="grid min-h-[calc(100vh-152px)] lg:grid-cols-2">

        <!-- Panel kiri: sign in -->
        <div class="flex items-center justify-center bg-white px-6 py-14 sm:px-10">
            <div class="w-full max-w-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Sign in</p>
                <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
                    Sign in to your account
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-gray-600">
                    Use your campus email to reach your quizzes, attempts and results.
                </p>

                <hr class="my-6 border-gray-200">

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="text-xs font-bold uppercase tracking-wide text-gray-700">Email</label>
                        <x-text-input id="email" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@student.uns.ac.id" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <label for="password" class="text-xs font-bold uppercase tracking-wide text-gray-700">Password</label>
                        <x-text-input id="password" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="password" name="password" required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="inline-flex items-center">
                            <input id="remember_me" type="checkbox" class="rounded-none border-gray-300 text-[#DC3B1C] shadow-sm focus:ring-[#DC3B1C]" name="remember">
                            <span class="ms-2 text-sm text-gray-600">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-sm font-semibold text-[#DC3B1C] underline" href="{{ route('password.request') }}">
                                Forgot your password?
                            </a>
                        @endif
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                        <span>Log in</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>

                    <hr class="my-6 border-gray-200">

                    @if (Route::has('register'))
                        <p class="text-sm text-gray-600">
                            No account yet?
                            <a class="font-semibold text-[#111111] underline" href="{{ route('register') }}">Register here</a>
                        </p>
                    @endif
                </form>
            </div>
        </div>

        <!-- Panel kanan: kode kuis -->
        <div class="flex items-center bg-[#DC3B1C] px-6 py-14 text-white sm:px-10">
            <div class="w-full max-w-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-white/70">Students</p>
                <h2 class="mt-2 text-3xl font-extrabold leading-tight sm:text-4xl">
                    Have a quiz code?
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-white/80">
                    Enter the six-character code from your lecturer. Sign in and you go straight to the quiz.
                </p>

                <form method="GET" action="{{ route('mahasiswa.quizzes.join') }}" class="mt-8">
                    <label for="quiz_code" class="text-xs font-bold uppercase tracking-wide text-white/70">Quiz code</label>
                    <input
                        id="quiz_code"
                        type="text"
                        name="code"
                        maxlength="8"
                        placeholder="A7K9QZ"
                        class="mt-1.5 block w-full rounded-none border-0 bg-white px-4 py-3 text-center text-lg font-bold uppercase tracking-[0.4em] text-[#111111] placeholder:text-gray-300 focus:ring-2 focus:ring-black"
                    >
                    <button type="submit" class="mt-3 inline-flex w-full items-center justify-between rounded-none bg-white px-5 py-3 text-sm font-bold text-[#DC3B1C] transition hover:bg-gray-100">
                        <span>Find quiz</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <hr class="my-8 border-white/20">

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-bold text-white/60">01</p>
                        <p class="mt-1 text-sm leading-snug text-white/90">Shuffled multiple-choice questions</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-white/60">02</p>
                        <p class="mt-1 text-sm leading-snug text-white/90">Timed attempts, one sitting</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-white/60">03</p>
                        <p class="mt-1 text-sm leading-snug text-white/90">Automatic scoring, Excel export</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
