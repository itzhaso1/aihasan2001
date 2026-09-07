<?php

namespace App\Services\Domain;

use App\Models\Website\WebsiteDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SslService
{
    /**
     * Provision a real TLS certificate for the domain via the configured driver.
     * Never marks ssl_status=active unless a valid certificate is verified on disk.
     *
     * @return array<string, mixed>
     */
    public function requestProvisioning(WebsiteDomain $domain): array
    {
        $domain->update([
            'ssl_status' => 'pending',
            'status' => in_array($domain->status, ['verified', 'ssl_pending', 'active'], true)
                ? 'ssl_pending'
                : $domain->status,
        ]);

        if (! (bool) config('website.ssl.enabled', false)) {
            return [
                'status' => 'pending',
                'driver' => 'disabled',
                'message' => 'SSL is pending. Enable WEBSITE_SSL_ENABLED and configure certbot on the Linux host.',
                'certificate_verified' => false,
            ];
        }

        $driver = strtolower((string) config('website.ssl.driver', 'certbot'));
        if ($driver !== 'certbot') {
            throw new RuntimeException("Unsupported SSL driver [{$driver}].");
        }

        try {
            $result = $this->provisionWithCertbot($domain);
            $inspection = $this->inspectCertificate($domain->normalized_domain);

            if (! ($inspection['valid'] ?? false)) {
                $domain->update([
                    'ssl_status' => 'failed',
                    'metadata' => array_merge(
                        is_array($domain->metadata) ? $domain->metadata : [],
                        [
                            'ssl' => [
                                'last_error' => 'Certificate files were not found or are invalid after provisioning.',
                                'provision_result' => $result,
                                'inspection' => $inspection,
                            ],
                        ]
                    ),
                ]);

                return [
                    'status' => 'failed',
                    'driver' => 'certbot',
                    'message' => 'SSL provisioning ran but certificate validation failed.',
                    'certificate_verified' => false,
                    'result' => $result,
                    'inspection' => $inspection,
                ];
            }

            $domain->update([
                'ssl_status' => 'active',
                'ssl_expires_at' => $inspection['expires_at'] ?? null,
                'status' => 'active',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    [
                        'ssl' => [
                            'driver' => 'certbot',
                            'certificate_path' => $inspection['certificate_path'] ?? null,
                            'fullchain_path' => $inspection['fullchain_path'] ?? null,
                            'privkey_path' => $inspection['privkey_path'] ?? null,
                            'provisioned_at' => now()->toIso8601String(),
                            'expires_at' => optional($inspection['expires_at'] ?? null)?->toIso8601String(),
                            'hosts' => $inspection['hosts'] ?? [],
                        ],
                    ]
                ),
            ]);

            $this->reloadWebServer();

            return [
                'status' => 'active',
                'driver' => 'certbot',
                'message' => 'SSL certificate provisioned and verified.',
                'certificate_verified' => true,
                'expires_at' => optional($inspection['expires_at'] ?? null)?->toIso8601String(),
                'result' => $result,
                'inspection' => $inspection,
            ];
        } catch (\Throwable $exception) {
            Log::error('ssl.provision_failed', [
                'domain' => $domain->normalized_domain,
                'error' => $exception->getMessage(),
            ]);

            $domain->update([
                'ssl_status' => 'failed',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    [
                        'ssl' => [
                            'last_error' => $exception->getMessage(),
                            'failed_at' => now()->toIso8601String(),
                        ],
                    ]
                ),
            ]);

            throw $exception;
        }
    }

    /**
     * Renew/check certificate and sync local SSL status from on-disk evidence only.
     *
     * @return array<string, mixed>
     */
    public function renewAndSync(WebsiteDomain $domain): array
    {
        if (! (bool) config('website.ssl.enabled', false)) {
            return [
                'renew_exit_code' => null,
                'renew_successful' => false,
                'renew_skipped' => true,
                'renew_output' => 'SSL disabled; synced from filesystem only.',
                'sync' => $this->syncFromFilesystem($domain),
            ];
        }

        $bin = (string) config('website.ssl.certbot_bin', 'certbot');

        try {
            $process = Process::timeout((int) config('website.ssl.command_timeout', 180))
                ->run([$bin, 'renew', '--cert-name', $domain->normalized_domain, '--quiet', '--no-random-sleep-on-renew']);

            $sync = $this->syncFromFilesystem($domain);

            return [
                'renew_exit_code' => $process->exitCode(),
                'renew_successful' => $process->successful(),
                'renew_skipped' => false,
                'renew_output' => $this->redactSecrets($process->output().$process->errorOutput()),
                'sync' => $sync,
            ];
        } catch (\Throwable $exception) {
            // Safe when certbot binary is missing or renew fails unexpectedly.
            Log::warning('ssl.renew_and_sync_failed', [
                'domain' => $domain->normalized_domain,
                'error' => $exception->getMessage(),
            ]);

            return [
                'renew_exit_code' => null,
                'renew_successful' => false,
                'renew_skipped' => true,
                'renew_output' => $this->redactSecrets($exception->getMessage()),
                'sync' => $this->syncFromFilesystem($domain),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function syncFromFilesystem(WebsiteDomain $domain): array
    {
        $inspection = $this->inspectCertificate($domain->normalized_domain);

        if (($inspection['valid'] ?? false) === true) {
            $expiresAt = $inspection['expires_at'] ?? null;
            $expired = $expiresAt instanceof Carbon && $expiresAt->lte(now());

            $domain->update([
                'ssl_status' => $expired ? 'expired' : 'active',
                'ssl_expires_at' => $expiresAt,
                'status' => $expired
                    ? $domain->status
                    : (in_array($domain->status, ['verified', 'ssl_pending', 'active'], true) ? 'active' : $domain->status),
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    [
                        'ssl' => array_merge(
                            is_array(data_get($domain->metadata, 'ssl')) ? data_get($domain->metadata, 'ssl') : [],
                            [
                                'synced_at' => now()->toIso8601String(),
                                'expires_at' => optional($expiresAt)?->toIso8601String(),
                                'certificate_path' => $inspection['certificate_path'] ?? null,
                            ]
                        ),
                    ]
                ),
            ]);

            return [
                'status' => $expired ? 'expired' : 'active',
                'certificate_verified' => ! $expired,
                'inspection' => $inspection,
            ];
        }

        if (in_array($domain->ssl_status, ['active', 'pending'], true)) {
            $domain->update([
                'ssl_status' => $domain->ssl_status === 'active' ? 'failed' : $domain->ssl_status,
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    [
                        'ssl' => array_merge(
                            is_array(data_get($domain->metadata, 'ssl')) ? data_get($domain->metadata, 'ssl') : [],
                            [
                                'synced_at' => now()->toIso8601String(),
                                'last_error' => 'Certificate missing or invalid on filesystem.',
                                'inspection' => $inspection,
                            ]
                        ),
                    ]
                ),
            ]);
        }

        return [
            'status' => $domain->fresh()->ssl_status,
            'certificate_verified' => false,
            'inspection' => $inspection,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function provisionWithCertbot(WebsiteDomain $domain): array
    {
        $bin = (string) config('website.ssl.certbot_bin', 'certbot');
        $email = trim((string) config('website.ssl.email'));
        $webroot = trim((string) config('website.ssl.webroot'));
        $hosts = $this->certificateHosts($domain);

        if ($email === '' || $webroot === '') {
            throw new RuntimeException('WEBSITE_SSL_EMAIL and WEBSITE_SSL_WEBROOT are required for certbot provisioning.');
        }

        $command = [
            $bin,
            'certonly',
            '--non-interactive',
            '--agree-tos',
            '--email',
            $email,
            '--webroot',
            '-w',
            $webroot,
            '--cert-name',
            $domain->normalized_domain,
            '--keep-until-expiring',
        ];

        foreach ($hosts as $host) {
            $command[] = '-d';
            $command[] = $host;
        }

        $process = Process::timeout((int) config('website.ssl.command_timeout', 180))->run($command);
        $output = $this->redactSecrets($process->output().$process->errorOutput());

        if (! $process->successful()) {
            throw new RuntimeException('Certbot provisioning failed: '.$output);
        }

        return [
            'exit_code' => $process->exitCode(),
            'hosts' => $hosts,
            'output' => $output,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectCertificate(string $normalizedDomain): array
    {
        $livePath = rtrim((string) config('website.ssl.live_path', '/etc/letsencrypt/live'), '/');
        $fullchain = $livePath.'/'.$normalizedDomain.'/fullchain.pem';
        $privkey = $livePath.'/'.$normalizedDomain.'/privkey.pem';
        $cert = $livePath.'/'.$normalizedDomain.'/cert.pem';

        if (! is_readable($fullchain) || ! is_readable($privkey)) {
            return [
                'valid' => false,
                'reason' => 'certificate_files_missing',
                'fullchain_path' => $fullchain,
                'privkey_path' => $privkey,
            ];
        }

        $certificatePem = @file_get_contents($cert) ?: @file_get_contents($fullchain);
        if (! is_string($certificatePem) || $certificatePem === '') {
            return [
                'valid' => false,
                'reason' => 'certificate_unreadable',
                'fullchain_path' => $fullchain,
            ];
        }

        $parsed = openssl_x509_parse($certificatePem);
        if (! is_array($parsed)) {
            return [
                'valid' => false,
                'reason' => 'certificate_parse_failed',
                'fullchain_path' => $fullchain,
            ];
        }

        $validTo = isset($parsed['validTo_time_t']) ? Carbon::createFromTimestamp((int) $parsed['validTo_time_t']) : null;
        $validFrom = isset($parsed['validFrom_time_t']) ? Carbon::createFromTimestamp((int) $parsed['validFrom_time_t']) : null;
        $now = now();
        $hosts = $this->extractSanHosts($parsed);

        $coversDomain = in_array(strtolower($normalizedDomain), $hosts, true)
            || in_array('www.'.strtolower($normalizedDomain), $hosts, true)
            || str_contains(strtolower((string) ($parsed['subject']['CN'] ?? '')), strtolower($normalizedDomain));

        $valid = $coversDomain
            && $validFrom instanceof Carbon
            && $validTo instanceof Carbon
            && $validFrom->lte($now)
            && $validTo->gt($now);

        return [
            'valid' => $valid,
            'reason' => $valid ? 'ok' : 'certificate_invalid_or_expired_or_host_mismatch',
            'certificate_path' => $cert,
            'fullchain_path' => $fullchain,
            'privkey_path' => $privkey,
            'expires_at' => $validTo,
            'valid_from' => $validFrom,
            'hosts' => $hosts,
            'subject' => $parsed['subject'] ?? [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function certificateHosts(WebsiteDomain $domain): array
    {
        $hosts = [$domain->normalized_domain];
        if ((bool) config('website.ssl.include_www', true)) {
            $hosts[] = 'www.'.$domain->normalized_domain;
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, string>
     */
    private function extractSanHosts(array $parsed): array
    {
        $hosts = [];
        $cn = strtolower((string) ($parsed['subject']['CN'] ?? ''));
        if ($cn !== '') {
            $hosts[] = $cn;
        }

        $san = (string) ($parsed['extensions']['subjectAltName'] ?? '');
        foreach (explode(',', $san) as $part) {
            $part = trim($part);
            if (str_starts_with(strtolower($part), 'dns:')) {
                $hosts[] = strtolower(substr($part, 4));
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function reloadWebServer(): void
    {
        $reload = trim((string) config('website.ssl.reload_command', ''));
        if ($reload === '') {
            return;
        }

        $process = Process::timeout(60)->run(['bash', '-lc', $reload]);
        if (! $process->successful()) {
            Log::warning('ssl.reload_failed', [
                'command' => $reload,
                'output' => $this->redactSecrets($process->output().$process->errorOutput()),
            ]);
        }
    }

    private function redactSecrets(string $output): string
    {
        $patterns = [
            '/ApiKey=[^&\s]+/i',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\s]+/i',
        ];

        return (string) preg_replace($patterns, '[REDACTED]', $output);
    }
}
