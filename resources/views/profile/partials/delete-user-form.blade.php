<p class="text-muted small">
    Setelah akun dihapus, semua data (riwayat kuis, nilai, dll.) akan hilang PERMANEN. Unduh data
    yang Anda butuhkan sebelum melanjutkan, kalau ada.
</p>

<form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Yakin ingin menghapus akun Anda? Tindakan ini TIDAK BISA dibatalkan.')">
    @csrf
    @method('delete')

    <div class="mb-3" style="max-width: 350px;">
        <label class="form-label">Konfirmasi Password</label>
        <input type="password" name="password" class="form-control">
        @error('password', 'userDeletion')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-danger">Hapus Akun Saya</button>
</form>
