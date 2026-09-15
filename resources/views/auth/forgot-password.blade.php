<x-guest-layout>
    <div class="mx-auto flex min-h-[calc(100vh-152px)] max-w-md flex-col justify-center px-6 py-14 sm:px-0">
        <p class="text-xs font-bold uppercase tracking-wide text-[#DC3B1C]">Reset password</p>
        <h1 class="mt-2 text-3xl font-extrabold leading-tight text-[#111111] sm:text-4xl">
            Forgot your password?
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            No problem. Let us know your email address and we will email you a password reset link.
        </p>

        <hr class="my-6 border-gray-200">

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="text-xs font-bold uppercase tracking-wide text-gray-700">Email</label>
                <x-text-input id="email" class="block mt-1.5 w-full rounded-none border-gray-300 focus:border-[#DC3B1C] focus:ring-[#DC3B1C]" type="email" name="email" :value="old('email')" required autofocus placeholder="name@student.uns.ac.id" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-between rounded-none bg-black px-5 py-3 text-sm font-bold text-white transition hover:bg-[#DC3B1C]">
                <span>Email Password Reset Link</span>
                <span aria-hidden="true">&rarr;</span>
            </button>

            <hr class="my-6 border-gray-200">

            <p class="text-sm text-gray-600">
                Sudah ingat password?
                <a class="font-semibold text-[#111111] underline" href="{{ route('login') }}">Kembali ke sign in</a>
            </p>
        </form>
    </div>
</x-guest-layout>
