@extends('live.layout')
@section('title', 'Gabung Kuis')
@section('body')
<div class="bar"><span>KUIS ONLINE</span><small>Kuis interaktif</small></div>
<div class="wrap">
    <div class="kartu">
        <h1>Gabung kuis</h1>
        <p>Scan QR di layar dosen, atau ketik kode 6 karakter.</p>
        <form method="POST" action="{{ route('live.enter.submit') }}">
            @csrf
            <input type="text" name="code" maxlength="6" autocomplete="off" autocapitalize="characters"
                   placeholder="KODE" value="{{ old('code') }}" style="text-transform:uppercase;letter-spacing:.2em;text-align:center;font-weight:800" required>
            @error('code') <div class="galat">{{ $message }}</div> @enderror
            <button class="btn penuh" style="margin-top:12px">Masuk</button>
        </form>
    </div>
</div>
@endsection
