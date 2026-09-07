<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\EmailWebhookEvent;

class ResendWebhookService
{
    /**
     * Verify Resend (Svix) webhook signature.
     *
     * @param  array{svix-id?:string,svix-timestamp?:string,svix-signature?:string}  $headers
     * @return array{verified:bool,reason:?string}
     */
    public function verifySignature(array $headers, string $rawBody): array
    {
        $secret = (string) config('services.resend.webhook_secret', '');
        if ($secret === '') {
            return [
                'verified' => false,
                'reason' => 'Resend webhook secret is not configured.',
            ];
        }

        $msgId = trim((string) ($headers['svix-id'] ?? ''));
        $timestamp = trim((string) ($headers['svix-timestamp'] ?? ''));
        $signatureHeader = trim((string) ($headers['svix-signature'] ?? ''));

        if ($msgId === '' || $timestamp === '' || $signatureHeader === '') {
            return [
                'verified' => false,
                'reason' => 'Missing Svix signature headers.',
            ];
        }

        if (! ctype_digit($timestamp)) {
            return [
                'verified' => false,
                'reason' => 'Invalid Svix timestamp.',
            ];
        }

        $tolerance = (int) config('services.resend.webhook_tolerance_seconds', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return [
                'verified' => false,
                'reason' => 'Svix timestamp outside tolerance window.',
            ];
        }

        $key = $this->decodeSigningKey($secret);
        if ($key === null) {
            return [
                'verified' => false,
                'reason' => 'Invalid Resend webhook secret format.',
            ];
        }

        $signedContent = $msgId.'.'.$timestamp.'.'.$rawBody;
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $key, true));

        $candidates = [];
        foreach (preg_split('/\s+/', $signatureHeader) ?: [] as $part) {
            if (str_starts_with($part, 'v1,')) {
                $candidates[] = substr($part, 3);
            } elseif ($part !== '') {
                $candidates[] = $part;
            }
        }

        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return [
                    'verified' => true,
                    'reason' => null,
                ];
            }
        }

        return [
            'verified' => false,
            'reason' => 'Svix signature mismatch.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): EmailWebhookEvent
    {
        $eventType = (string) ($payload['type'] ?? 'unknown');
        $providerMessageId = $this->extractProviderMessageId($payload);
        $emailLog = null;

        if ($providerMessageId !== '') {
            $emailLog = EmailLog::query()
                ->where('provider', 'resend')
                ->where('provider_message_id', $providerMessageId)
                ->latest('id')
                ->first();
        }

        $event = EmailWebhookEvent::query()->create([
            'email_log_id' => $emailLog?->id,
            'provider' => 'resend',
            'event_type' => $eventType,
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        if ($emailLog) {
            if (in_array($eventType, ['email.delivered', 'delivered'], true)) {
                $emailLog->forceFill([
                    'status' => 'sent',
                    'error' => null,
                ])->save();
            }

            if (in_array($eventType, ['email.bounced', 'bounced'], true)) {
                $emailLog->forceFill([
                    'status' => 'failed',
                    'error' => 'Resend webhook reported bounce.',
                ])->save();
            }
        }

        return $event;
    }

    private function decodeSigningKey(string $secret): ?string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 6), true);
            if ($decoded === false || $decoded === '') {
                return null;
            }

            return $decoded;
        }

        return $secret;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderMessageId(array $payload): string
    {
        $direct = $payload['message_id'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            $candidate = $data['email_id'] ?? $data['id'] ?? $data['message_id'] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
