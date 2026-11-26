<?php

namespace Tests\Feature;

use App\Models\LivePlayer;
use App\Models\LiveSession;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\LiveGameService;
use App\Support\LiveStateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LiveGameTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;
    private Quiz $quiz;
    private string $dir;
    /** @var array<int, int> question id => correct option id */
    private array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/live-' . uniqid());
        config(['live.state_dir' => $this->dir, 'live.state_url' => 'http://localhost/live']);

        $this->dosen = $this->makeTeacher();
        $this->quiz = Quiz::create([
            'dosen_id' => $this->dosen->id, 'title' => 'Kuis Jaringan', 'duration_minutes' => 30,
            'max_attempts' => 1, 'is_published' => false,
        ]);

        foreach ([1, 2, 3] as $n) {
            $q = Question::create(['quiz_id' => $this->quiz->id, 'question_text' => "Soal {$n}", 'order' => $n, 'type' => 'pilihan_ganda']);
            foreach (['A', 'B', 'C', 'D'] as $i => $label) {
                $o = Option::create(['question_id' => $q->id, 'option_text' => "Opsi {$label}{$n}", 'is_correct' => $i === 1, 'order' => $i]);
                if ($i === 1) {
                    $this->keys[$q->id] = $o->id;
                }
            }
        }
        // Tidak ikut dimainkan.
        Question::create(['quiz_id' => $this->quiz->id, 'question_text' => 'Esai', 'order' => 9, 'type' => 'esai']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function game(): LiveGameService
    {
        return app(LiveGameService::class);
    }

    private function newSession(string $mode = 'live', int $seconds = 20): LiveSession
    {
        $this->actingAs($this->dosen)->post(route('dosen.live.store'), [
            'quiz_id' => $this->quiz->id, 'mode' => $mode, 'seconds_per_question' => $seconds,
        ])->assertRedirect();
        auth()->logout();

        return LiveSession::latest('id')->firstOrFail();
    }

    private function state(LiveSession $s): array
    {
        return app(LiveStateFile::class)->read($s->state_token);
    }

    /** Bergabung sebagai tamu (tanpa login) dan kembalikan pemainnya. */
    private function joinAsGuest(LiveSession $s, string $name): LivePlayer
    {
        $this->flushSession();
        $this->post(route('live.join.submit', $s->code), ['nickname' => $name])
            ->assertRedirect(route('live.play', $s->code));

        return LivePlayer::where('nickname', $name)->firstOrFail();
    }

    private function asPlayer(LivePlayer $p): static
    {
        return $this->withSession(['live_player.' . $p->live_session_id => $p->id]);
    }

    public function test_points_formula(): void
    {
        $this->assertSame(1000, LiveGameService::points(0, 20000, 1));
        $this->assertSame(750, LiveGameService::points(10000, 20000, 1));
        $this->assertSame(500, LiveGameService::points(20000, 20000, 1));
        $this->assertSame(850, LiveGameService::points(10000, 20000, 2) + 0 - 0); // +100 beruntun
        $this->assertSame(1250, LiveGameService::points(10000, 20000, 9));        // bonus maks 500
    }

    public function test_session_uses_only_multiple_choice_and_writes_state_file(): void
    {
        $s = $this->newSession();

        $this->assertSame('lobby', $s->status);
        $this->assertSame(3, $s->questionCount());
        $this->assertSame(6, strlen($s->code));
        $this->assertSame('lobby', $this->state($s)['status']);
        $this->assertFileExists($this->dir . '/' . $s->state_token . '.json');
    }

    public function test_quiz_without_playable_questions_is_rejected(): void
    {
        $empty = Quiz::create(['dosen_id' => $this->dosen->id, 'title' => 'Kosong', 'duration_minutes' => 10, 'max_attempts' => 1]);

        $this->actingAs($this->dosen)->get(route('dosen.live.create', ['quiz' => $empty->id, 'mode' => 'live']))
            ->assertOk()->assertSee('belum punya soal pilihan ganda');
        $this->actingAs($this->dosen)->post(route('dosen.live.store'), [
            'quiz_id' => $empty->id, 'mode' => 'live', 'seconds_per_question' => 20,
        ])->assertSessionHasErrors('quiz');
        $this->assertSame(0, LiveSession::count());
    }

    public function test_guest_joins_by_scanning_without_login(): void
    {
        $s = $this->newSession();

        $this->get(route('live.join', $s->code))->assertOk()->assertSee('Kuis Jaringan')->assertSee('Gabung');
        $p = $this->joinAsGuest($s, 'Budi');
        $this->assertNull($p->user_id);
        $this->assertSame(1, $this->state($s)['players']);

        $this->asPlayer($p)->get(route('live.play', $s->code))->assertOk()->assertSee('Budi');

        // Nama sama ditolak (tanpa membedakan huruf besar/kecil).
        $this->flushSession();
        $this->post(route('live.join.submit', $s->code), ['nickname' => 'budi'])->assertSessionHasErrors('nickname');

        // Kode lewat formulir.
        $this->post(route('live.enter.submit'), ['code' => strtolower($s->code)])->assertRedirect(route('live.join', $s->code));
        $this->get(route('live.join', 'ZZZZZZ'))->assertRedirect(route('live.enter'));
    }

    public function test_logged_in_student_joins_once_with_account(): void
    {
        $s = $this->newSession();
        $mhs = User::factory()->create(['role' => 'mahasiswa', 'name' => 'Ani Mahasiswa']);

        $this->actingAs($mhs)->get(route('live.join', $s->code))->assertSee('Ani Mahasiswa');
        $this->actingAs($mhs)->post(route('live.join.submit', $s->code), ['nickname' => 'Ani'])->assertRedirect();
        $this->flushSession();
        // Perangkat lain dengan akun yang sama langsung dikenali.
        $this->actingAs($mhs)->get(route('live.join', $s->code))->assertRedirect(route('live.play', $s->code));

        $this->assertSame(1, LivePlayer::count());
        $this->assertSame($mhs->id, LivePlayer::first()->user_id);
    }

    public function test_live_round_scoring_reveal_and_finish(): void
    {
        Carbon::setTestNow('2026-11-27 08:00:00');
        $s = $this->newSession('live', 20);
        $budi = $this->joinAsGuest($s, 'Budi');
        $cici = $this->joinAsGuest($s, 'Cici');
        $dodi = $this->joinAsGuest($s, 'Dodi');

        $this->actingAs($this->dosen)->postJson(route('dosen.live.start', $s))->assertOk()->assertJson(['status' => 'question', 'index' => 0]);
        auth()->logout();

        $state = $this->state($s);
        $q = $state['question'];
        $this->assertSame('question', $state['status']);
        $this->assertArrayNotHasKey('reveal', $state);
        $this->assertStringNotContainsString('is_correct', json_encode($state));
        $correct = $this->keys[$q['id']];
        $wrong = collect($q['options'])->pluck('id')->first(fn ($id) => $id !== $correct);

        Carbon::setTestNow(now()->addSeconds(5));
        $this->asPlayer($budi)->postJson(route('live.answer', $s->code), ['question_id' => $q['id'], 'option_id' => $correct, 'elapsed_ms' => 4000])
            ->assertOk()->assertExactJson(['received' => true]);
        // Mengaku terlalu cepat (0 ms padahal server 5 detik): dibatasi 2,5 detik.
        $this->asPlayer($cici)->postJson(route('live.answer', $s->code), ['question_id' => $q['id'], 'option_id' => $correct, 'elapsed_ms' => 0])->assertOk();
        // Jawaban ganda ditolak.
        $this->asPlayer($budi)->postJson(route('live.answer', $s->code), ['question_id' => $q['id'], 'option_id' => $wrong])->assertStatus(422);

        $this->assertSame(LiveGameService::points(4000, 20000, 1), $budi->fresh()->score);
        $this->assertSame(LiveGameService::points(2500, 20000, 1), $cici->fresh()->score);

        $this->actingAs($this->dosen)->getJson(route('dosen.live.status', $s))->assertJson(['answered' => 2])->assertJsonCount(3, 'players');
        $this->actingAs($this->dosen)->postJson(route('dosen.live.reveal', $s), ['index' => 0])->assertJson(['status' => 'reveal']);
        auth()->logout();

        $state = $this->state($s);
        $this->assertSame($correct, $state['reveal']['correct_option_id']);
        $this->assertSame(2, $state['reveal']['counts'][(string) $correct]);
        $this->assertSame('Cici', $state['reveal']['top'][0]['nickname']);

        // Terlambat setelah soal ditutup.
        $this->asPlayer($dodi)->postJson(route('live.answer', $s->code), ['question_id' => $q['id'], 'option_id' => $correct])->assertStatus(422);

        $this->asPlayer($budi)->getJson(route('live.me', $s->code) . '?question_id=' . $q['id'])
            ->assertJson(['rank' => 2, 'players' => 3, 'last' => ['answered' => true, 'correct' => true]]);
        $this->asPlayer($dodi)->getJson(route('live.me', $s->code) . '?question_id=' . $q['id'])
            ->assertJson(['rank' => 3, 'last' => ['answered' => false]]);

        // Soal 2: waktu habis lalu jawaban ditolak.
        $this->actingAs($this->dosen)->postJson(route('dosen.live.next', $s))->assertJson(['index' => 1]);
        auth()->logout();
        $q2 = $this->state($s)['question'];
        Carbon::setTestNow(now()->addSeconds(22));
        $this->asPlayer($dodi)->postJson(route('live.answer', $s->code), ['question_id' => $q2['id'], 'option_id' => $this->keys[$q2['id']]])->assertStatus(422);

        // Permintaan tutup ganda dengan indeks lama diabaikan.
        $this->actingAs($this->dosen)->postJson(route('dosen.live.reveal', $s), ['index' => 0])->assertJson(['status' => 'question']);
        $this->actingAs($this->dosen)->postJson(route('dosen.live.reveal', $s), ['index' => 1])->assertJson(['status' => 'reveal']);
        $this->assertSame(0, $budi->fresh()->streak, 'tidak menjawab memutus beruntun');

        $this->actingAs($this->dosen)->postJson(route('dosen.live.next', $s))->assertJson(['index' => 2]);
        $this->actingAs($this->dosen)->postJson(route('dosen.live.reveal', $s));
        $this->actingAs($this->dosen)->postJson(route('dosen.live.next', $s))->assertJson(['status' => 'finished']);

        $final = $this->state($s);
        $this->assertSame('finished', $final['status']);
        $this->assertSame(['Cici', 'Budi', 'Dodi'], collect($final['top'])->pluck('nickname')->all());

        $this->actingAs($this->dosen)->get(route('dosen.live.results', $s))->assertOk()->assertSee('Cici')->assertSee('Soal 1');
        $this->actingAs($this->dosen)->get(route('dosen.live.export', $s))->assertOk()->assertDownload();
    }

    public function test_host_can_kick_player(): void
    {
        $s = $this->newSession();
        $p = $this->joinAsGuest($s, 'NamaJelek');

        $this->actingAs($this->dosen)->postJson(route('dosen.live.kick', [$s, $p]))->assertOk();
        auth()->logout();

        $this->assertNull($p->fresh());
        $this->asPlayer($p)->getJson(route('live.me', $s->code))->assertForbidden();
        $this->asPlayer($p)->get(route('live.play', $s->code))->assertRedirect(route('live.join', $s->code));
    }

    public function test_other_teacher_cannot_host_or_view(): void
    {
        $s = $this->newSession();
        $lain = $this->makeTeacher();

        $this->actingAs($lain)->get(route('dosen.live.show', $s))->assertForbidden();
        $this->actingAs($lain)->postJson(route('dosen.live.start', $s))->assertForbidden();
        $this->actingAs($lain)->get(route('dosen.live.results', $s))->assertForbidden();
        $this->actingAs($lain)->get(route('dosen.live.create', ['quiz' => $this->quiz->id]))->assertForbidden();

        $this->actingAs($this->dosen)->get(route('dosen.live.show', $s))->assertOk()->assertSee($s->code);
        $this->actingAs($this->dosen)->get(route('dosen.live.qr', $s))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->actingAs($this->dosen)->get(route('dosen.live.index'))->assertOk()->assertSee($s->code);
    }

    public function test_challenge_self_paced_with_feedback_timeout_and_close(): void
    {
        Carbon::setTestNow('2026-11-27 09:00:00');
        $s = $this->newSession('challenge', 30);
        $this->assertSame('open', $s->status);

        $budi = $this->joinAsGuest($s, 'Budi');
        $this->assertEqualsCanonicalizing(collect($s->plan)->pluck('q')->all(), collect($budi->plan)->pluck('q')->all());

        $this->asPlayer($budi)->get(route('live.play', $s->code))->assertOk()->assertSee('Mulai');

        // Soal 1: benar setelah 6 detik.
        $d = $this->asPlayer($budi)->getJson(route('live.challenge.question', $s->code))->assertOk()
            ->assertJson(['index' => 0, 'total' => 3, 'seconds' => 30])->json();
        $this->assertStringNotContainsString('is_correct', json_encode($d));
        Carbon::setTestNow(now()->addSeconds(6));
        $r = $this->asPlayer($budi)->postJson(route('live.challenge.answer', $s->code), [
            'question_id' => $d['question']['id'], 'option_id' => $this->keys[$d['question']['id']],
        ])->assertOk()->json();
        $this->assertTrue($r['correct']);
        $this->assertSame(LiveGameService::points(6000, 30000, 1), $r['points']);
        $this->assertSame($this->keys[$d['question']['id']], $r['correct_option_id']);

        // Menjawab soal yang sama lagi ditolak.
        $this->asPlayer($budi)->postJson(route('live.challenge.answer', $s->code), [
            'question_id' => $d['question']['id'], 'option_id' => $this->keys[$d['question']['id']],
        ])->assertStatus(422);

        // Soal 2: dijawab setelah batas waktu, dihitung tidak menjawab.
        $d2 = $this->asPlayer($budi)->getJson(route('live.challenge.question', $s->code))->assertJson(['index' => 1])->json();
        Carbon::setTestNow(now()->addSeconds(40));
        $r2 = $this->asPlayer($budi)->postJson(route('live.challenge.answer', $s->code), [
            'question_id' => $d2['question']['id'], 'option_id' => $this->keys[$d2['question']['id']],
        ])->json();
        $this->assertFalse($r2['correct']);
        $this->assertSame(0, $r2['points']);
        $this->assertSame(0, $r2['streak']);

        // Pemain lain dan papan peringkat dosen.
        $cici = $this->joinAsGuest($s, 'Cici');
        $this->actingAs($this->dosen)->getJson(route('dosen.live.status', $s))
            ->assertJsonPath('leaderboard.0.nickname', 'Budi')
            ->assertJsonPath('leaderboard.0.progress', 2)
            ->assertJsonPath('leaderboard.1.nickname', 'Cici');
        auth()->logout();

        // Dosen menutup tantangan: soal tidak bisa lanjut.
        $this->actingAs($this->dosen)->post(route('dosen.live.finish', $s))->assertRedirect(route('dosen.live.results', $s));
        auth()->logout();
        $this->asPlayer($cici)->getJson(route('live.challenge.question', $s->code))->assertJson(['finished' => true, 'closed' => true]);
        $this->asPlayer($budi)->getJson(route('live.leaderboard', $s->code))
            ->assertJsonPath('top.0.nickname', 'Budi')->assertJsonPath('me.rank', 1);

        // Sesi selesai tidak menerima peserta baru.
        $this->flushSession();
        $this->post(route('live.join.submit', $s->code), ['nickname' => 'Telat'])->assertSessionHasErrors('nickname');
    }

    public function test_challenge_player_finishing_all_questions(): void
    {
        $s = $this->newSession('challenge', 30);
        $p = $this->joinAsGuest($s, 'Rajin');

        for ($i = 0; $i < 3; $i++) {
            $d = $this->asPlayer($p)->getJson(route('live.challenge.question', $s->code))->json();
            $r = $this->asPlayer($p)->postJson(route('live.challenge.answer', $s->code), [
                'question_id' => $d['question']['id'], 'option_id' => $this->keys[$d['question']['id']],
            ])->json();
        }

        $this->assertTrue($r['finished']);
        $this->assertSame(3, $r['streak']);
        $this->assertNotNull($p->fresh()->finished_at);
        $this->asPlayer($p)->getJson(route('live.challenge.question', $s->code))->assertJson(['finished' => true, 'closed' => false]);
    }

    public function test_quiz_list_offers_live_and_challenge(): void
    {
        $this->actingAs($this->dosen)->get(route('dosen.quizzes.index'))->assertOk()
            ->assertSee(route('dosen.live.create', ['quiz' => $this->quiz->id, 'mode' => 'live']))
            ->assertSee('Kuis Interaktif');
    }

    public function test_cleanup_removes_only_old_state_files(): void
    {
        $s = $this->newSession();
        $old = $this->dir . '/lama.json';
        file_put_contents($old, '{}');
        touch($old, time() - 72 * 3600);

        $this->artisan('live:cleanup', ['--hours' => 48])->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($this->dir . '/' . $s->state_token . '.json');
    }
}
