@extends('errors.layout')
@section('code', '403')
@section('title', 'Tidak punya akses')
@section('body')
    <p>{{ $exception->getMessage() ?: 'Akun Anda tidak punya akses ke halaman ini.' }}</p>
    <p>Kalau menurut Anda ini keliru, pastikan Anda masuk dengan akun yang benar.</p>
@endsection
