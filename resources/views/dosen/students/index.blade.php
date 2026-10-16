@extends('layouts.app')
@section('title', 'Daftar Mahasiswa')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Daftar Mahasiswa</h3>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted small">
            Total: <strong>{{ $students->total() }}</strong>
            @if(!empty($angkatan) || !empty($kelas)) (terfilter) @endif
        </span>
        <a href="{{ route('dosen.students.import.form') }}" class="btn btn-primary btn-sm">+ Import Mahasiswa</a>
    </div>
</div>

{{-- Combo Angkatan dulu --}}
<div class="mb-2">
    <label class="form-label small text-muted mb-1">Angkatan</label>
    <div class="btn-group flex-wrap">
        <a href="{{ route('dosen.students.index', array_filter(['kelas' => $kelas ?? null])) }}"
           class="btn btn-sm {{ empty($angkatan) ? 'btn-primary' : 'btn-outline-primary' }}">
            Semua Angkatan
        </a>
        @foreach (['2026', '2025', '2024'] as $a)
            <a href="{{ route('dosen.students.index', array_filter(['angkatan' => $a, 'kelas' => $kelas ?? null])) }}"
               class="btn btn-sm {{ ($angkatan ?? null) === $a ? 'btn-primary' : 'btn-outline-primary' }}">
                {{ $a }}
            </a>
        @endforeach
    </div>
</div>

{{-- Combo Kelas --}}
<div class="mb-3">
    <label class="form-label small text-muted mb-1">Kelas</label>
    <div class="btn-group flex-wrap">
        <a href="{{ route('dosen.students.index', array_filter(['angkatan' => $angkatan ?? null])) }}"
           class="btn btn-sm {{ empty($kelas) ? 'btn-success' : 'btn-outline-success' }}">
            Semua Kelas
        </a>
        @foreach (\App\Models\User::KELAS as $k)
            <a href="{{ route('dosen.students.index', array_filter(['angkatan' => $angkatan ?? null, 'kelas' => $k])) }}"
               class="btn btn-sm {{ ($kelas ?? null) === $k ? 'btn-success' : 'btn-outline-success' }}">
                Kelas {{ $k }}
            </a>
        @endforeach
    </div>
</div>

@if(!empty($angkatan) || !empty($kelas))
    <p class="small text-muted mb-3">
        Filter aktif:
        @if(!empty($angkatan)) <span class="badge bg-dark">Angkatan {{ $angkatan }}</span> @endif
        @if(!empty($kelas)) <span class="badge bg-success">Kelas {{ $kelas }}</span> @endif
        <a href="{{ route('dosen.students.index') }}" class="ms-1">[hapus filter]</a>
    </p>
@endif

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th style="width:40px">#</th>
            <th>Nama</th>
            <th>NIM</th>
            <th>Kelas</th>
            <th>Angkatan</th>
            <th>Email</th>
            <th>Terdaftar</th>
            <th style="width:220px">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $s)
            @php
                $detectedAngkatan = '-';
                $nim = $s->nim ?? '';
                if (str_starts_with($nim, 'K3524')) {
                    $detectedAngkatan = '2024';
                } elseif (str_starts_with($nim, 'K3525')) {
                    $detectedAngkatan = '2025';
                } elseif (preg_match('/^[0-9]{10,}$/', $nim) || (strlen($nim) >= 10 && !str_starts_with($nim, 'K352'))) {
                    $detectedAngkatan = '2026';
                }
            @endphp
            <tr>
                <td>{{ $students->firstItem() + $loop->index }}</td>
                <td>{{ $s->name }}</td>
                <td><code>{{ $s->nim ?? '-' }}</code></td>
                <td>{{ $s->kelas ?? '-' }}</td>
                <td>{{ $detectedAngkatan }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ $s->created_at->format('d-m-Y') }}</td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#editModal"
                        data-id="{{ $s->id }}"
                        data-name="{{ $s->name }}"
                        data-nim="{{ $s->nim }}"
                        data-kelas="{{ $s->kelas }}"
                        data-email="{{ $s->email }}"
                        data-action="{{ route('dosen.students.update', $s) }}">Edit</button>
                    <button type="button" class="btn btn-sm btn-warning"
                        data-bs-toggle="modal" data-bs-target="#passwordModal"
                        data-name="{{ $s->name }}"
                        data-email="{{ $s->email }}"
                        data-action="{{ route('dosen.students.password', $s) }}">Password</button>
                    <button type="button" class="btn btn-sm btn-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                        data-name="{{ $s->name }}"
                        data-email="{{ $s->email }}"
                        data-action="{{ route('dosen.students.destroy', $s) }}">Hapus</button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-muted">
                    Belum ada mahasiswa
                    @if(!empty($angkatan) || !empty($kelas)) dengan filter ini @endif.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $students->links() }}

{{-- MODAL EDIT --}}
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
                <div class="mb-3">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIM</label>
                    <input type="text" name="nim" id="edit_nim" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" id="edit_kelas" class="form-select">
                        <option value="">— Tidak ada —</option>
                        @foreach (\App\Models\User::KELAS as $k)
                            <option value="{{ $k }}">{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL PASSWORD --}}
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="passwordForm" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Ubah Password — <span id="password_student_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Untuk konfirmasi, ketik ulang email mahasiswa ini:</p>
                <div class="mb-3">
                    <label class="form-label">Ketik ulang email mahasiswa</label>
                    <input type="email" name="confirm_email" id="password_confirm_email" class="form-control" required>
                    <div class="form-text">Email target: <code id="password_target_email"></code></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ulangi Password Baru</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">Ubah Password</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL HAPUS --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="deleteForm" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title">Hapus Mahasiswa — <span id="delete_student_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    Tindakan ini akan menghapus akun mahasiswa beserta seluruh riwayat kuisnya secara permanen
                    dan tidak dapat dibatalkan.
                </div>
                <p class="small text-muted">Untuk konfirmasi, ketik ulang email mahasiswa ini:</p>
                <div class="mb-3">
                    <label class="form-label">Ketik ulang email mahasiswa</label>
                    <input type="email" name="confirm_email" id="delete_confirm_email" class="form-control" required>
                    <div class="form-text">Email target: <code id="delete_target_email"></code></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus Mahasiswa</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('editForm').action = btn.getAttribute('data-action') || '';
            document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
            document.getElementById('edit_nim').value = btn.getAttribute('data-nim') || '';
            document.getElementById('edit_kelas').value = btn.getAttribute('data-kelas') || '';
            document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
        });
    }

    var passwordModal = document.getElementById('passwordModal');
    if (passwordModal) {
        passwordModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('passwordForm').action = btn.getAttribute('data-action') || '';
            document.getElementById('password_student_name').textContent = btn.getAttribute('data-name') || '';
            document.getElementById('password_target_email').textContent = btn.getAttribute('data-email') || '';
            document.getElementById('password_confirm_email').value = '';
        });
    }

    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('deleteForm').action = btn.getAttribute('data-action') || '';
            document.getElementById('delete_student_name').textContent = btn.getAttribute('data-name') || '';
            document.getElementById('delete_target_email').textContent = btn.getAttribute('data-email') || '';
            document.getElementById('delete_confirm_email').value = '';
        });
    }
});
</script>
@endsection
