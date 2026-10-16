@php $isMahasiswa = $user->role === 'mahasiswa'; @endphp

<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="mb-3">
        <label class="form-label">Nama Lengkap</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required autofocus>
        @error('name')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    @if ($isMahasiswa)
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">NIM</label>
                <input type="text" name="nim" class="form-control" value="{{ old('nim', $user->nim) }}" required>
                @error('nim')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Kelas</label>
                <select name="kelas" class="form-control" required>
                    <option value="" disabled>Pilih kelas</option>
                    @foreach (\App\Models\User::KELAS as $opt)
                        <option value="{{ $opt }}" {{ old('kelas', $user->kelas) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
                @error('kelas')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
        @error('email')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
            <div class="text-warning small mt-2">
                Email Anda belum terverifikasi.
                <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">Kirim ulang link verifikasi.</button>
            </div>

            @if (session('status') === 'verification-link-sent')
                <div class="text-success small mt-1">Link verifikasi baru sudah dikirim ke email Anda.</div>
            @endif
        @endif
    </div>

    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>

    @if (session('status') && session('status') !== 'profile-updated' && session('status') !== 'verification-link-sent')
        <span class="text-success ms-2">{{ session('status') }}</span>
    @elseif (session('status') === 'profile-updated')
        <span class="text-success ms-2">Profil berhasil diperbarui.</span>
    @endif
</form>

<form id="send-verification" method="POST" action="{{ route('verification.send') }}">
    @csrf
</form>
