<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Server email menolak alamat tujuan (mis. "550 No Such User Here")
            // atau tidak bisa dihubungi. Tampilkan pesan, jangan error 500.
            report($e);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Email reset tidak bisa dikirim ke alamat ini. Pastikan alamat email tersebut benar-benar bisa menerima email, '
                    . 'atau masuk dengan Google bila akun ini terhubung dengan Google.',
            ]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }
}
