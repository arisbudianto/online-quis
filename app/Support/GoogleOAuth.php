<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Alur authorization code Google tanpa paket tambahan (pakai HTTP client
 * Laravel). Identitas diambil dari endpoint userinfo OpenID Connect memakai
 * access token yang baru ditukar langsung dengan Google lewat TLS, sehingga
 * tidak perlu memverifikasi tanda tangan id_token sendiri.
 */
class GoogleOAuth
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function authorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online',
        ]);
    }

    /**
     * Tukar code dengan profil Google yang emailnya sudah diverifikasi Google.
     *
     * @return array{sub: string, email: string, name: string}
     */
    public static function profileFromCode(string $code): array
    {
        $token = Http::asForm()->acceptJson()->timeout(10)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$token->successful() || !$token->json('access_token')) {
            throw new RuntimeException('Gagal menukar kode Google: HTTP ' . $token->status() . ' ' . $token->json('error', ''));
        }

        $info = Http::withToken($token->json('access_token'))->acceptJson()->timeout(10)->get(self::USERINFO_URL);

        if (!$info->successful() || !$info->json('sub') || !$info->json('email')) {
            throw new RuntimeException('Gagal membaca profil Google: HTTP ' . $info->status());
        }

        $verified = $info->json('email_verified');
        if ($verified !== true && $verified !== 'true') {
            throw new GoogleEmailNotVerified($info->json('email'));
        }

        return [
            'sub' => (string) $info->json('sub'),
            'email' => strtolower(trim((string) $info->json('email'))),
            'name' => trim((string) ($info->json('name') ?: $info->json('email'))),
        ];
    }
}
