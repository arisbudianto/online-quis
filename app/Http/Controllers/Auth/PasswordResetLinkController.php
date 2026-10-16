<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\GoogleOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /** Hasil yang punya halaman sendiri. */
    private const RESULTS = ['sent', 'unknown', 'undeliverable'];

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim link reset. Apa pun hasilnya (terkirim, email belum terdaftar,
     * atau server email menolak alamat), pengguna diarahkan ke halaman
     * hasil yang menjelaskan langkah berikutnya, bukan error 500.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($request->input('email')));

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (TransportExceptionInterface $e) {
            // Contoh 16-10-2026: "550 No Such User Here" untuk alamat
            // yang kotak suratnya tidak ada.
            report($e);
            $status = 'undeliverable';
        }

        $result = match ($status) {
            Password::RESET_LINK_SENT => 'sent',
            Password::INVALID_USER => 'unknown',
            'undeliverable' => 'undeliverable',
            default => null,
        };

        if ($result === null) {
            // Mis. terlalu sering meminta (throttle): tetap di formulir.
            return back()->withInput(['email' => $email])->withErrors(['email' => __($status)]);
        }

        return redirect()->route('password.result')->with('reset_result', ['state' => $result, 'email' => $email]);
    }

    public function result(Request $request): View|RedirectResponse
    {
        $result = $request->session()->get('reset_result');

        if (!is_array($result) || !in_array($result['state'] ?? null, self::RESULTS, true)) {
            return redirect()->route('password.request');
        }

        // Tetap bisa dimuat ulang selama sesi ini.
        $request->session()->keep('reset_result');

        return view('auth.forgot-password-result', [
            'state' => $result['state'],
            'email' => $result['email'],
            'google' => GoogleOAuth::enabled(),
        ]);
    }
}
