@extends('live.layout')
@section('title', 'Papan ' . $session->code)
@section('body')
<div class="bar">
    <span>{{ $session->quiz->title ?? 'Kuis' }} <small>· {{ $session->modeLabel() }} · {{ $session->code }}</small></span>
    <small id="info"></small>
</div>
<div class="wrap lebar">
    <div id="awards-box" class="hidden">
        <h1 class="tengah">Penghargaan</h1>
        <div class="penghargaan" id="awards"></div>
    </div>
    <div class="baris">
        <div class="kartu pemimpin" id="pemimpin" style="flex:0 1 360px"></div>
        <div class="kartu">
            <h2>Papan peringkat</h2>
            <p class="kecil">Bintang = penjawab benar tercepat pada satu soal. Diperbarui otomatis.</p>
            <table class="papan">
                <thead><tr><th>#</th><th>Nama</th><th class="angka">Benar</th><th class="angka">Skor</th></tr></thead>
                <tbody id="papan"></tbody>
            </table>
        </div>
    </div>
    <p class="kecil tengah">Ikut bermain: buka {{ preg_replace('#^https?://#', '', route('live.join', $session->code)) }}</p>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const L = window.LIVE;
    const URL = @json(route('live.board.data', $session->code));
    const $ = id => document.getElementById(id);
    const label = { lobby: 'Menunggu dimulai', question: 'Soal sedang berjalan', reveal: 'Pembahasan', open: 'Tantangan dibuka', finished: 'Selesai' };
    async function poll() {
        try {
            const d = await L.getJson(URL);
            $('info').textContent = d.players + ' peserta · ' + (label[d.status] || d.status) +
                (d.mode === 'live' && d.index >= 0 && d.status !== 'finished' ? ' · soal ' + (d.index + 1) + '/' + d.total : '');
            const top = d.leaderboard[0];
            $('pemimpin').innerHTML = top
                ? '<p class="kecil" style="margin:0">' + (d.status === 'finished' ? 'Juara 1' : 'Pemimpin sementara') + '</p><div class="nama">' +
                  L.esc(top.nickname) + L.stars(top.stars) + '</div><p class="kecil" style="margin:0">' + top.score + ' poin · ' + top.correct + ' benar</p>'
                : '<p class="kecil" style="margin:0">Belum ada peserta.</p>';
            $('papan').innerHTML = d.leaderboard.map(p => '<tr><td>' + p.rank + '</td><td>' + L.esc(p.nickname) + L.stars(p.stars) +
                '</td><td class="angka">' + p.correct + '</td><td class="angka">' + p.score + '</td></tr>').join('');
            $('awards-box').classList.toggle('hidden', !d.awards.length);
            $('awards').innerHTML = L.awardCards(d.awards);
            if (d.status === 'finished') return;
        } catch (e) {}
        setTimeout(poll, 3000);
    }
    poll();
})();
</script>
@endsection
