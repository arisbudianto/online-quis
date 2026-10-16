<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountCredentialsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LecturerController extends Controller
{
    public function index()
    {
        // Dosen yang menunggu persetujuan selalu tampil di atas.
        $pending = User::where('role', 'dosen')->whereNull('approved_at')
            ->orderBy('created_at')
            ->get();

        $lecturers = User::where('role', 'dosen')->whereNotNull('approved_at')
            ->withCount('courses', 'quizzes', 'students')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.lecturers.index', compact('lecturers', 'pending'));
    }

    public function approve(User $lecturer)
    {
        abort_unless($lecturer->isPendingApproval(), 404);

        $lecturer->forceFill(['approved_at' => now()])->save();

        try {
            $lecturer->notify(new \App\Notifications\LecturerApprovedNotification());
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', "Akun dosen/guru {$lecturer->name} disetujui.");
    }

    /** Menolak = menghapus akun yang belum pernah aktif (tanpa kuis/mata kuliah). */
    public function reject(User $lecturer)
    {
        abort_unless($lecturer->isPendingApproval(), 404);
        abort_if($lecturer->courses()->exists() || $lecturer->quizzes()->exists(), 409, 'Akun ini sudah punya data.');

        $name = $lecturer->name;
        $lecturer->delete();

        return back()->with('status', "Pendaftaran {$name} ditolak dan akunnya dihapus.");
    }

    public function create()
    {
        return view('admin.lecturers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $plainPassword = Str::random(10);

        $lecturer = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'role' => 'dosen',
        ]);
        // Dibuat langsung oleh admin: tidak perlu persetujuan lagi.
        $lecturer->forceFill(['approved_at' => now()])->save();

        $sendEmail = $request->boolean('send_email', true);

        if ($sendEmail) {
            try {
                $lecturer->notify(new AccountCredentialsNotification($plainPassword));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $status = "Akun dosen \"{$lecturer->name}\" berhasil dibuat.";
        $status .= $sendEmail
            ? ' Email kredensial sudah dikirim.'
            : " Password: {$plainPassword} (catat sekarang, tidak ditampilkan lagi).";

        return redirect()->route('admin.lecturers.index')->with('status', $status);
    }
}
