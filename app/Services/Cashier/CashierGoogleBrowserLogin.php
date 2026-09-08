<?php

namespace App\Services\Cashier;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class CashierGoogleBrowserLogin
{
    public const TTL_SECONDS = 600;

    public static function cacheKey(string $ticket): string
    {
        return 'cashier.google.ticket.'.$ticket;
    }

    public function configured(): bool
    {
        return trim((string) config('services.google.client_id')) !== ''
            && trim((string) config('services.google.client_secret')) !== '';
    }

    /**
     * @return array{ticket: string, auth_url: string, expires_in: int}
     */
    public function start(): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('يحتاج إعداد Google في ملف .env على Laravel (GOOGLE_CLIENT_ID و GOOGLE_CLIENT_SECRET).');
        }

        $ticket = (string) Str::uuid();
        Cache::put(self::cacheKey($ticket), [
            'status' => 'pending',
            'access_token' => null,
            'error' => null,
        ], self::TTL_SECONDS);

        $redirect = trim((string) config('services.google.redirect'));
        if ($redirect === '') {
            config(['services.google.redirect' => url('/auth/google/callback')]);
        }

        $authUrl = Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'state' => $ticket,
                'prompt' => 'select_account',
            ])
            ->redirect()
            ->getTargetUrl();

        return [
            'ticket' => $ticket,
            'auth_url' => $authUrl,
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    public function isCashierTicket(string $state): bool
    {
        $state = trim($state);
        if ($state === '') {
            return false;
        }

        return Cache::has(self::cacheKey($state));
    }

    public function complete(string $provider, string $ticket): Response
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $token = trim((string) ($socialUser->token ?? ''));
            if ($token === '') {
                $this->mark($ticket, 'failed', error: 'لم يرجع Google رمز الدخول.');

                return $this->htmlPage('فشل تسجيل الدخول عبر Google.', false);
            }
            $this->mark($ticket, 'ready', accessToken: $token);

            return $this->htmlPage('تم تسجيل الدخول عبر Google. أغلق هذه النافذة وعد إلى كاشير حاسم.', true);
        } catch (Throwable) {
            $this->mark($ticket, 'failed', error: 'فشل تسجيل الدخول عبر Google.');

            return $this->htmlPage('فشل تسجيل الدخول عبر Google.', false);
        }
    }

    /**
     * @return array{status: string, access_token: ?string, error: ?string}
     */
    public function status(string $ticket): array
    {
        $payload = Cache::get(self::cacheKey($ticket));
        if (! is_array($payload)) {
            return [
                'status' => 'expired',
                'access_token' => null,
                'error' => 'انتهت جلسة Google. أعد المحاولة.',
            ];
        }

        $status = (string) ($payload['status'] ?? 'pending');
        $token = isset($payload['access_token']) ? trim((string) $payload['access_token']) : '';
        $error = isset($payload['error']) ? (string) $payload['error'] : null;

        if ($status === 'ready' && $token !== '') {
            Cache::forget(self::cacheKey($ticket));

            return [
                'status' => 'ready',
                'access_token' => $token,
                'error' => null,
            ];
        }

        return [
            'status' => $status,
            'access_token' => null,
            'error' => $error,
        ];
    }

    private function mark(string $ticket, string $status, ?string $accessToken = null, ?string $error = null): void
    {
        Cache::put(self::cacheKey($ticket), [
            'status' => $status,
            'access_token' => $accessToken,
            'error' => $error,
        ], self::TTL_SECONDS);
    }

    private function htmlPage(string $message, bool $ok): Response
    {
        $color = $ok ? '#049E86' : '#DC2626';
        $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>كاشير حاسم</title></head>'
            .'<body style="font-family:sans-serif;padding:40px;text-align:center;color:#0F172A">'
            .'<h1 style="color:'.$color.'">حاسم</h1>'
            .'<p>'.e($message).'</p>'
            .'</body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
