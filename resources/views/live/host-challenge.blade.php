@extends('live.layout')
@section('title', 'Tantangan ' . $session->code)
@section('body')
<div class="bar">
    <span>{{ $session->quiz->title ?? 'Kuis' }} <small>· Tantangan · {{ $session->code }}</small></span>
    <a class="btn putih" style="padding:6px 12px;font-size:13px" href="{{ route('dosen.live.index') }}">Keluar layar</a>
</div>
<div class="wrap lebar">
    <div class="baris">
        <div class="kartu tengah" style="flex:0 1 380px">
            <p>Scan QR atau buka <strong>{{ preg_replace('#^https?://#', '', route('live.enter')) }}</strong></p>
            <img src="{{ route('dosen.live.qr', $session) }}" alt="QR gabung" style="width:100%;max-width:320px">
            <div class="kode-besar">{{ $session->code }}</div>
            <p class="kecil">{{ $session->questionCount() }} soal · {{ $session->seconds_per_question }} detik per soal</p>
                <a class="btn putih" style="padding:8px 14px;font-size:14px;margin:8px 0" href="{{ route('live.board', $session->code) }}" target="_blank" rel="noopener">Buka papan bersama</a>
                <p class="kecil">Untuk TV atau layar kedua, tanpa login: {{ preg_replace('#^https?://#', '', route('live.board', $session->code)) }}</p>
            @if ($session->isFinished())
                <p><strong>Tantangan sudah ditutup.</strong></p>
                <a class="btn penuh" href="{{ route('dosen.live.results', $session) }}">Hasil lengkap</a>
            @else
                <form method="POST" action="{{ route('dosen.live.finish', $session) }}" onsubmit="return confirm('Tutup tantangan? Peserta yang belum selesai tidak bisa melanjutkan.')">
                    @csrf
                    <button class="btn penuh">Tutup tantangan</button>
                </form>
            @endif
        </div>
        <div class="kartu">
            <div class="penghargaan" id="awards"></div>
            <h2>Papan peringkat</h2>
            <p class="kecil"><span id="jml">0</span> peserta · <span id="selesai">0</span> selesai · diperbarui tiap 3 detik</p>
            <table class="papan">
                <thead><tr><th>#</th><th>Nama</th><th class="angka">Progres</th><th class="angka">Benar</th><th class="angka">Skor</th></tr></thead>
                <tbody id="papan"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const L = window.LIVE;
    const URL = @json(route('dosen.live.status', $session));
    const $ = id => document.getElementById(id);
    async function poll() {
        try {
            const d = await L.getJson(URL);
            $('jml').textContent = d.leaderboard.length;
            $('selesai').textContent = d.leaderboard.filter(p => p.finished).length;
            $('papan').innerHTML = d.leaderboard.map(p => '<tr><td>' + p.rank + '</td><td>' + L.esc(p.nickname) + L.stars(p.stars) + '</td><td class="angka">' +
                (p.finished ? 'selesai' : p.progress + '/' + d.total) + '</td><td class="angka">' + p.correct + '</td><td class="angka">' + p.score + '</td></tr>').join('')
                || '<tr><td colspan="5" class="kecil">Belum ada peserta.</td></tr>';
            $('awards').innerHTML = L.awardCards(d.awards);
        } catch (e) {}
        setTimeout(poll, 3000);
    }
    poll();
})();
</script>
@endsection
