@extends('layouts.app')
@section('title', 'Peserta ' . $course->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Peserta {{ $course->name }}</h3>
    <div class="d-flex gap-2">
        @if ($period)
            <a href="{{ route('dosen.recap.index', ['periode' => $period->id, 'mk' => $course->id]) }}" class="btn btn-outline-success btn-sm">📈 Rekap Nilai</a>
        @endif
        <a href="{{ route('dosen.courses.index') }}" class="btn btn-outline-secondary btn-sm">← Mata Kuliah</a>
    </div>
</div>

<form method="GET" action="{{ route('dosen.enrollments.index', $course) }}" class="row g-2 align-items-end mb-3">
    <div class="col-sm-5">
        <label class="form-label small mb-1">Periode</label>
        <select name="periode" class="form-select form-select-sm" onchange="this.form.submit()">
            @forelse ($periods as $p)
                <option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->label }}{{ $p->is_active ? ' (aktif)' : '' }}</option>
            @empty
                <option value="">Belum ada periode, minta admin menambahkan</option>
            @endforelse
        </select>
    </div>
</form>

@if ($period)
<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Tambah dari daftar NIM</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dosen.enrollments.storeNims', $course) }}">
                    @csrf
                    <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                    <textarea name="nims" rows="6" class="form-control form-control-sm mb-2" required
                              placeholder="Satu NIM per baris, atau tempel kolom NIM dari Excel">{{ old('nims') }}</textarea>
                    <button class="btn btn-primary btn-sm">Tambahkan</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Tambah satu kelas</div>
            <div class="card-body">
                <form method="POST" action="{{ route('dosen.enrollments.storeKelas', $course) }}" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                    <div class="col-5">
                        <label class="form-label small mb-1">Kelas</label>
                        <select name="kelas" class="form-select form-select-sm" required>
                            @foreach ($kelasOptions as $k)
                                <option value="{{ $k }}">{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-7">
                        <label class="form-label small mb-1">Awalan NIM (opsional)</label>
                        <input type="text" name="nim_prefix" class="form-control form-control-sm" placeholder="contoh K3526">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-primary btn-sm">Tambahkan kelas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <p class="text-muted small mb-2">{{ $enrollments->count() }} peserta terdaftar di {{ $period->label }}.</p>
        <table class="table table-bordered table-sm bg-white align-middle">
            <thead class="table-light">
                <tr><th>NIM</th><th>Nama</th><th>Kelas</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $e)
                    <tr>
                        <td>{{ $e->student->nim }}</td>
                        <td>{{ $e->student->name }}</td>
                        <td>{{ $e->student->kelas }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('dosen.enrollments.destroy', [$course, $e]) }}"
                                  onsubmit="return confirm('Hapus {{ addslashes($e->student->name) }} dari peserta?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
