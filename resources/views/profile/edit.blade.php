@extends('layouts.app')
@section('title', 'Edit Profil')

@section('content')
<h3>Edit Profil</h3>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header">Informasi Profil</div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        @if ($user->role === 'mahasiswa')
            @php
                $myTeachers = $user->teachers()->orderBy('name')->get(['users.id', 'name', 'institution']);
                $otherTeachers = \App\Models\User::approvedTeachers()->whereNotIn('id', $myTeachers->pluck('id'))
                    ->orderBy('name')->get(['id', 'name', 'institution']);
            @endphp
            <div class="card mb-4">
                <div class="card-header">Dosen / Guru Saya</div>
                <div class="card-body">
                    <p class="small text-muted">Daftar kuis dan pilihan kelas Anda mengikuti dosen/guru di sini.</p>
                    @error('teacher_id')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror
                    <ul class="list-group mb-3">
                        @forelse ($myTeachers as $t)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $t->name }} @if ($t->institution)<span class="text-muted small">· {{ $t->institution }}</span>@endif</span>
                                @if ($myTeachers->count() > 1)
                                    <form method="POST" action="{{ route('profile.teachers.remove', $t->id) }}"
                                          onsubmit="return confirm('Lepas {{ addslashes($t->name) }} dari daftar dosen/guru Anda?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger py-0">Lepas</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Belum memilih dosen/guru.</li>
                        @endforelse
                    </ul>
                    @if ($otherTeachers->isNotEmpty())
                        <form method="POST" action="{{ route('profile.teachers.add') }}" class="d-flex gap-2">
                            @csrf
                            <select name="teacher_id" class="form-select" required>
                                <option value="">Tambah dosen/guru</option>
                                @foreach ($otherTeachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}{{ $t->institution ? ' · ' . $t->institution : '' }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary">Tambah</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">Ubah Password</div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header text-danger">Hapus Akun</div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
@endsection
