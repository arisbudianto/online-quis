@extends('layouts.app')
@section('title', 'Periode Akademik')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Periode Akademik</h3>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Admin</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.periods.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-sm-4">
                <label class="form-label">Tahun ajaran mulai</label>
                <input type="number" name="year_start" class="form-control" min="2000" max="2100"
                       value="{{ old('year_start', now()->month >= 8 ? now()->year : now()->year - 1) }}" required>
                <div class="form-text">Isi 2026 untuk tahun ajaran 2026/2027.</div>
            </div>
            <div class="col-sm-3">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select" required>
                    @foreach ($semesters as $value => $label)
                        <option value="{{ $value }}" @selected(old('semester') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active">
                    <label for="is_active" class="form-check-label">Jadikan periode aktif</label>
                </div>
            </div>
            <div class="col-sm-2">
                <button class="btn btn-primary w-100">Tambah</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-bordered bg-white align-middle">
    <thead>
        <tr>
            <th>Periode</th>
            <th>Jumlah Kuis</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($periods as $p)
            <tr>
                <td>{{ $p->label }}</td>
                <td>{{ $p->quizzes_count }}</td>
                <td>
                    @if ($p->is_active)
                        <span class="badge bg-success">Aktif</span>
                    @endif
                </td>
                <td class="text-end">
                    @unless ($p->is_active)
                        <form method="POST" action="{{ route('admin.periods.activate', $p) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-success">Jadikan aktif</button>
                        </form>
                    @endunless
                    @if ($p->quizzes_count === 0)
                        <form method="POST" action="{{ route('admin.periods.destroy', $p) }}" class="d-inline"
                              onsubmit="return confirm('Hapus periode {{ $p->label }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">Belum ada periode. Tambahkan periode yang sedang berjalan.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
