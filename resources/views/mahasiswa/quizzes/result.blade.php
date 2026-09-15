@extends('layouts.app')
@section('title', 'Hasil Kuis')

@section('content')
<div class="card">
    <div class="card-body text-center">
        <h3>{{ $attempt->quiz->title }}</h3>
        <p class="text-muted">Diselesaikan pada {{ optional($attempt->submitted_at)->format('d-m-Y H:i') }}</p>
        <h1 class="display-4">{{ $attempt->score }}</h1>
        <p>dari 100</p>
        <a href="{{ route('mahasiswa.quizzes.index') }}" class="btn btn-primary">Kembali ke Daftar Kuis</a>
    </div>
</div>
@endsection
