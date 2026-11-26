@extends('live.layout')
@section('title', 'Gabung ' . $session->code)
@section('body')
<div class="bar"><span>KUIS ONLINE</span><small>{{ $session->modeLabel() }} · {{ $session->code }}</small></div>
<div class="wrap">
    <div class="kartu">
        <p class="kecil" style="margin-bottom:4px">{{ $session->isLive() ? 'Kuis live' : 'Tantangan' }}</p>
        <h1>{{ $session->quiz->title ?? 'Kuis' }}</h1>
        @if ($session->isFinished())
            <p>Sesi ini sudah selesai. Minta kode sesi yang baru ke dosen/guru Anda.</p>
        @else
            <p>{{ $session->isLive()
                ? 'Soal muncul bergantian sesuai aba-aba dosen. Jawab cepat untuk poin lebih besar.'
                : 'Kerjakan soal satu per satu dengan kecepatan Anda sendiri. Jawaban benar dan cepat dapat poin lebih besar.' }}</p>
            <form method="POST" action="{{ route('live.join.submit', $session->code) }}">
                @csrf
                <label class="kecil" for="nickname">Nama yang tampil di papan peringkat</label>
                <input type="text" id="nickname" name="nickname" maxlength="30" required autofocus
                       value="{{ old('nickname', $user?->role === 'mahasiswa' ? $user->name : '') }}" placeholder="Nama Anda">
                @error('nickname') <div class="galat">{{ $message }}</div> @enderror
                <button class="btn penuh" style="margin-top:12px">Gabung</button>
            </form>
            <p class="kecil" style="margin-top:12px">Latihan ini tidak masuk nilai semester.</p>
        @endif
    </div>
</div>
@endsection
