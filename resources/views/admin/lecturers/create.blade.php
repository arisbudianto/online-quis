@extends('layouts.app')
@section('title', 'Buat Akun Dosen')

@section('content')
<h3>Buat Akun Dosen Baru</h3>

<form method="POST" action="{{ route('admin.lecturers.store') }}" class="bg-white p-4 rounded shadow-sm" style="max-width: 500px;">
    @csrf

    <div class="mb-3">
        <label class="form-label">Nama Lengkap</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="send_email" value="1" class="form-check-input" id="send_email" checked>
        <label class="form-check-label" for="send_email">
            Kirim email kredensial (password digenerate otomatis)
        </label>
    </div>

    <button class="btn btn-primary">Buat Akun</button>
    <a href="{{ route('admin.lecturers.index') }}" class="btn btn-link">Batal</a>
</form>
@endsection
