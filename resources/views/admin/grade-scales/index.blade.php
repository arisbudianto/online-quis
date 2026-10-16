@extends('layouts.app')
@section('title', 'Konversi Nilai Huruf')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Konversi Nilai Huruf</h3>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Admin</a>
</div>

<p class="text-muted small">
    Nilai akhir mata kuliah dikonversi ke huruf yang batas bawahnya terpenuhi, dibandingkan apa adanya
    tanpa pembulatan (84,99 = A-, 85 = A). Perubahan langsung berlaku di semua rekap.
</p>

<form method="POST" action="{{ route('admin.gradeScales.update') }}">
    @csrf
    @method('PUT')
    <table class="table table-bordered bg-white align-middle" style="max-width: 560px">
        <thead>
            <tr>
                <th>Huruf</th>
                <th>Batas bawah (≥)</th>
                <th>Bobot (skala 4)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($scales as $s)
                <tr>
                    <td class="fw-bold">{{ $s->letter }}</td>
                    <td>
                        <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                               name="scales[{{ $s->id }}][min_score]"
                               value="{{ old('scales.' . $s->id . '.min_score', rtrim(rtrim(number_format($s->min_score, 2, '.', ''), '0'), '.')) }}"
                               @if ($loop->last) readonly @endif required>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" max="4" class="form-control form-control-sm"
                               name="scales[{{ $s->id }}][weight]"
                               value="{{ old('scales.' . $s->id . '.weight', number_format($s->weight, 2, '.', '')) }}" required>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button class="btn btn-primary">Simpan</button>
</form>
@endsection
