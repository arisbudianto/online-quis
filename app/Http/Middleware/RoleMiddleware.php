<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Contoh pemakaian di routes: ->middleware('role:dosen,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        // Dosen/guru yang mendaftar sendiri belum boleh memakai menu dosen
        // sebelum disetujui admin.
        if ($user->isPendingApproval()) {
            return redirect()->route('dosen.pending');
        }

        return $next($request);
    }
}
