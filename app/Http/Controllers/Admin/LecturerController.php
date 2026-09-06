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
        $lecturers = User::where('role', 'dosen')
            ->withCount('courses', 'quizzes')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.lecturers.index', compact('lecturers'));
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
