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
    <div class="col-sm-3">
        <label class="form-label small mb-1">Angkatan (daftar peserta)</label>
        <select name="angkatan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Semua angkatan</option>
            @foreach (\App\Support\Angkatan::options() as $a => $aLabel)
                <option value="{{ $a }}" @selected($angkatan === (string) $a)>{{ $aLabel }}</option>
            @endforeach
        </select>
    </div>
</form>

@if ($period)
<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3 border-success">
            <div class="card-header">Daftarkan semua siswa Anda (kelas {{ $kelasOptions->implode(', ') ?: '-' }})</div>
            <div class="card-body">
                <table class="table table-sm table-bordered small mb-2">
                    <thead class="table-light">
                        <tr>
                            <th>Angkatan</th>
                            @foreach ($kelasOptions as $k)<th class="text-center">{{ $k }}</th>@endforeach
                            <th class="text-center">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($angkatanCounts as $a => $perKelas)
                            <tr>
                                <td>{{ \App\Support\Angkatan::label($a) }}</td>
                                @foreach ($kelasOptions as $k)<td class="text-center">{{ $perKelas[$k] ?? 0 }}</td>@endforeach
                                <td class="text-center fw-semibold">{{ array_sum($perKelas) }}</td>
                            </tr>
                        @endforeach
                        <tr class="table-light">
                            <td>Semua</td>
                            @foreach ($kelasOptions as $k)<td class="text-center">{{ $kelasCounts[$k] ?? 0 }}</td>@endforeach
                            <td class="text-center fw-semibold">{{ $kelasCounts->sum() }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="small text-muted mb-2">Siswa yang memilih Anda, menurut angkatan dan kelas. Yang sudah terdaftar dilewati.</p>
                <form method="POST" action="{{ route('dosen.enrollments.storeAllKelas', $course) }}" class="row g-2 align-items-end"
                      onsubmit="var s=this.angkatan; return confirm('Daftarkan semua siswa Anda kelas {{ addslashes($kelasOptions->implode(', ')) }}' + (s.value ? ' angkatan ' + s.options[s.selectedIndex].text : ' dari semua angkatan') + ' ke {{ addslashes($course->name) }} ({{ $period->label }})?')">
                    @csrf
                    <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                    <div class="col-7">
                        <label class="form-label small mb-1">Angkatan</label>
                        <select name="angkatan" class="form-select form-select-sm">
                            <option value="">Semua angkatan</option>
                            @foreach (\App\Support\Angkatan::options() as $a => $aLabel)
                                <option value="{{ $a }}">{{ $aLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <button class="btn btn-success btn-sm w-100">Daftarkan semua</button>
                    </div>
                    <div class="col-12 form-text">
                        Pilih angkatan bila mata kuliah ini hanya untuk satu angkatan, supaya angkatan lain tidak ikut terdaftar.
                    </div>
                </form>
            </div>
        </div>

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
                        <label class="form-label small mb-1">Angkatan</label>
                        <select name="angkatan" class="form-select form-select-sm">
                            <option value="">Semua angkatan</option>
                            @foreach (\App\Support\Angkatan::options() as $a => $aLabel)
                                <option value="{{ $a }}">{{ $aLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-primary btn-sm">Tambahkan kelas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <p class="text-muted small mb-2">{{ $enrollments->count() }} peserta terdaftar di {{ $period->label }}{{ $angkatan ? ', angkatan ' . \App\Support\Angkatan::label($angkatan) : '' }}.</p>
        <div class="table-responsive">
        <table class="table table-bordered table-sm bg-white align-middle">
            <thead class="table-light">
                <tr><th>NIM</th><th>Nama</th><th>Kelas</th><th>Angkatan</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $e)
                    <tr>
                        <td>{{ $e->student->nim }}</td>
                        <td>{{ $e->student->name }}</td>
                        <td>{{ $e->student->kelas }}</td>
                        <td>{{ $e->student->angkatan ?? 'Lainnya' }}</td>
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
                    <tr><td colspan="5" class="text-center text-muted">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif
@endsection
