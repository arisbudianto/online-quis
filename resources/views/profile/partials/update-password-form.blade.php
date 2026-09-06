<p class="text-muted small">Pastikan akun Anda memakai password yang panjang dan acak agar tetap aman.</p>

<form method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-3">
        <label class="form-label">Password Saat Ini</label>
        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
        @error('current_password', 'updatePassword')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password" class="form-control" autocomplete="new-password">
        @error('password', 'updatePassword')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label class="form-label">Konfirmasi Password Baru</label>
        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
        @error('password_confirmation', 'updatePassword')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">Simpan Password Baru</button>

    @if (session('status') === 'password-updated')
        <span class="text-success ms-2">Password berhasil diubah.</span>
    @endif
</form>
