@extends('layouts.app')
@section('title', 'Menunggu Persetujuan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-warning">
            <div class="card-body">
                <h4>Pendaftaran dosen/guru sedang diperiksa</h4>
                <p class="mb-2">
                    Akun <strong>{{ $user->email }}</strong> sudah terdaftar sebagai dosen/guru
                    @if ($user->institution) dari <strong>{{ $user->institution }}</strong>@endif.
                    Admin perlu menyetujuinya sebelum Anda bisa membuat kuis dan melihat siswa.
                </p>
                <p class="text-muted small mb-0">
                    Kami kirim email ke alamat di atas setelah akun disetujui. Halaman ini bisa dibuka lagi kapan saja
                    untuk mengecek status.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
