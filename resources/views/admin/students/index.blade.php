@extends('layouts.app')
@section('title', 'Semua Mahasiswa')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Semua Mahasiswa</h3>
    <span class="text-muted small">
        Total: <strong>{{ $students->total() }}</strong> mahasiswa
        @if($angkatan || $kelas)
            (terfilter)
        @endif
    </span>
</div>

{{-- Combo Angkatan dulu --}}
<div class="mb-2">
    <label class="form-label small text-muted mb-1">Angkatan</label>
    <div class="btn-group flex-wrap">
        <a href="{{ route('admin.students.index', array_filter(['kelas' => $kelas])) }}"
           class="btn btn-sm {{ !$angkatan ? 'btn-dark' : 'btn-outline-dark' }}">
            Semua Angkatan
        </a>
        @foreach (['2026', '2025', '2024'] as $a)
            <a href="{{ route('admin.students.index', array_filter(['angkatan' => $a, 'kelas' => $kelas])) }}"
               class="btn btn-sm {{ $angkatan === $a ? 'btn-dark' : 'btn-outline-dark' }}">
                {{ $a }}
            </a>
        @endforeach
    </div>
</div>

{{-- Combo Kelas sesudahnya --}}
<div class="mb-3">
    <label class="form-label small text-muted mb-1">Kelas</label>
    <div class="btn-group flex-wrap">
        <a href="{{ route('admin.students.index', array_filter(['angkatan' => $angkatan])) }}"
           class="btn btn-sm {{ !$kelas ? 'btn-primary' : 'btn-outline-primary' }}">
            Semua Kelas
        </a>
        @foreach (\App\Models\User::KELAS as $k)
            <a href="{{ route('admin.students.index', array_filter(['angkatan' => $angkatan, 'kelas' => $k])) }}"
               class="btn btn-sm {{ $kelas === $k ? 'btn-primary' : 'btn-outline-primary' }}">
                Kelas {{ $k }}
            </a>
        @endforeach
    </div>
</div>

@if($angkatan || $kelas)
    <p class="small text-muted mb-3">
        Filter aktif:
        @if($angkatan) <span class="badge bg-dark">Angkatan {{ $angkatan }}</span> @endif
        @if($kelas) <span class="badge bg-primary">Kelas {{ $kelas }}</span> @endif
        <a href="{{ route('admin.students.index') }}" class="ms-1">[hapus filter]</a>
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
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted">
                    Tidak ada mahasiswa
                    @if($angkatan || $kelas) dengan filter ini @endif.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $students->links() }}
@endsection
