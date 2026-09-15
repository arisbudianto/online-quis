@extends('layouts.app')
@section('title', 'Daftar Mahasiswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Daftar Mahasiswa</h3>
    <a href="{{ route('dosen.students.import.form') }}" class="btn btn-primary btn-sm">+ Import Mahasiswa</a>
</div>

<div class="btn-group mb-3">
    <a href="{{ route('dosen.students.index') }}" class="btn btn-sm {{ !$kelas ? 'btn-dark' : 'btn-outline-dark' }}">Semua</a>
    @foreach (['A', 'B', 'C'] as $k)
        <a href="{{ route('dosen.students.index', ['kelas' => $k]) }}" class="btn btn-sm {{ $kelas === $k ? 'btn-dark' : 'btn-outline-dark' }}">Kelas {{ $k }}</a>
    @endforeach
</div>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>Nama</th>
            <th>NIM</th>
            <th>Kelas</th>
            <th>Email</th>
            <th>Terdaftar</th>
            <th style="width: 220px;">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $s)
            <tr>
                <td>{{ $s->name }}</td>
                <td>{{ $s->nim }}</td>
                <td>{{ $s->kelas ?? '-' }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ $s->created_at->format('d-m-Y') }}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="{{ $s->id }}"
                            data-name="{{ $s->name }}"
                            data-nim="{{ $s->nim }}"
                            data-kelas="{{ $s->kelas }}"
                            data-email="{{ $s->email }}"
                            data-action="{{ route('dosen.students.update', $s) }}">
                            Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning"
                            data-bs-toggle="modal" data-bs-target="#passwordModal"
                            data-name="{{ $s->name }}"
                            data-email="{{ $s->email }}"
                            data-action="{{ route('dosen.students.password', $s) }}">
                            Ubah Password
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-name="{{ $s->name }}"
                            data-email="{{ $s->email }}"
                            data-action="{{ route('dosen.students.destroy', $s) }}">
                            Hapus
                        </button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Belum ada mahasiswa terdaftar.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $students->links() }}

{{-- ===================== MODAL: EDIT MAHASISWA ===================== --}}
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="editForm" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Data Mahasiswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">NIM</label>
                    <input type="text" name="nim" id="edit_nim" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" id="edit_kelas" class="form-control">
                        <option value="">— Tidak ada —</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- ===================== MODAL: UBAH PASSWORD ===================== --}}
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="passwordForm" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Ubah Password — <span id="password_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Untuk konfirmasi, ketik ulang email mahasiswa ini:
                    <strong id="password_email_hint"></strong>
                </p>
                <div class="mb-2">
                    <label class="form-label">Ketik ulang email mahasiswa</label>
                    <input type="email" name="confirm_email" class="form-control password-confirm-email" required>
                    <div class="form-text text-danger d-none password-mismatch-hint">Email belum cocok.</div>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Ulangi Password Baru</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning password-submit-btn" disabled>Ubah Password</button>
            </div>
        </form>
    </div>
</div>

{{-- ===================== MODAL: HAPUS MAHASISWA ===================== --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="deleteForm" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger">Hapus Mahasiswa — <span id="delete_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger">
                    Tindakan ini akan menghapus akun mahasiswa beserta seluruh riwayat kuisnya secara permanen
                    dan tidak dapat dibatalkan.
                </p>
                <p class="text-muted small">
                    Untuk konfirmasi, ketik ulang email mahasiswa ini:
                    <strong id="delete_email_hint"></strong>
                </p>
                <div class="mb-2">
                    <label class="form-label">Ketik ulang email mahasiswa</label>
                    <input type="email" name="confirm_email" class="form-control delete-confirm-email" required>
                    <div class="form-text text-danger d-none delete-mismatch-hint">Email belum cocok.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger delete-submit-btn" disabled>Hapus Mahasiswa</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // ---- Modal EDIT: isi form sesuai baris yang diklik ----
    document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('editForm').action = btn.dataset.action;
        document.getElementById('edit_name').value = btn.dataset.name || '';
        document.getElementById('edit_nim').value = btn.dataset.nim || '';
        document.getElementById('edit_kelas').value = btn.dataset.kelas || '';
        document.getElementById('edit_email').value = btn.dataset.email || '';
    });

    // ---- Modal PASSWORD: isi form + validasi konfirmasi email ----
    const passwordModal = document.getElementById('passwordModal');
    passwordModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const form = document.getElementById('passwordForm');
        form.action = btn.dataset.action;
        document.getElementById('password_name').textContent = btn.dataset.name || '';
        document.getElementById('password_email_hint').textContent = btn.dataset.email || '';
        form.dataset.targetEmail = (btn.dataset.email || '').trim().toLowerCase();
        form.querySelector('.password-confirm-email').value = '';
        form.querySelector('.password-submit-btn').disabled = true;
        form.querySelector('.password-mismatch-hint').classList.add('d-none');
    });
    passwordModal.querySelector('.password-confirm-email').addEventListener('input', function () {
        const form = document.getElementById('passwordForm');
        const match = this.value.trim().toLowerCase() === form.dataset.targetEmail;
        form.querySelector('.password-submit-btn').disabled = !match;
        form.querySelector('.password-mismatch-hint').classList.toggle('d-none', match || this.value === '');
    });

    // ---- Modal DELETE: isi form + validasi konfirmasi email ----
    const deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const form = document.getElementById('deleteForm');
        form.action = btn.dataset.action;
        document.getElementById('delete_name').textContent = btn.dataset.name || '';
        document.getElementById('delete_email_hint').textContent = btn.dataset.email || '';
        form.dataset.targetEmail = (btn.dataset.email || '').trim().toLowerCase();
        form.querySelector('.delete-confirm-email').value = '';
        form.querySelector('.delete-submit-btn').disabled = true;
        form.querySelector('.delete-mismatch-hint').classList.add('d-none');
    });
    deleteModal.querySelector('.delete-confirm-email').addEventListener('input', function () {
        const form = document.getElementById('deleteForm');
        const match = this.value.trim().toLowerCase() === form.dataset.targetEmail;
        form.querySelector('.delete-submit-btn').disabled = !match;
        form.querySelector('.delete-mismatch-hint').classList.toggle('d-none', match || this.value === '');
    });
</script>
@endsection
