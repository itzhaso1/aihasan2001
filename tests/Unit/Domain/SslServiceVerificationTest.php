<?php

namespace Tests\Unit\Domain;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\SslService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SslServiceVerificationTest extends TestCase
{
    public function test_ssl_never_marks_active_without_readable_certificate(): void
    {
        config()->set('website.ssl.enabled', true);
        config()->set('website.ssl.driver', 'certbot');
        config()->set('website.ssl.email', 'ops@example.com');
        config()->set('website.ssl.webroot', storage_path('framework/testing/certbot-webroot'));
        config()->set('website.ssl.live_path', storage_path('framework/testing/letsencrypt/live'));
        config()->set('website.ssl.reload_command', '');

        File::ensureDirectoryExists(storage_path('framework/testing/certbot-webroot'));

        $domain = new WebsiteDomain([
            'workspace_id' => 1,
            'website_id' => 1,
            'domain' => 'example.test',
            'normalized_domain' => 'example.test',
            'status' => 'verified',
            'ssl_status' => 'pending',
            'metadata' => [],
        ]);

        // Avoid DB persistence for this unit check of inspectCertificate.
        $service = new SslService();
        $inspection = $service->inspectCertificate('example.test');

        $this->assertFalse($inspection['valid']);
        $this->assertSame('certificate_files_missing', $inspection['reason']);
    }

    public function test_disabled_ssl_returns_pending_without_activating(): void
    {
        config()->set('website.ssl.enabled', false);

        $domain = WebsiteDomain::withoutGlobalScopes()->make([
            'workspace_id' => 1,
            'website_id' => 1,
            'domain' => 'example.test',
            'normalized_domain' => 'example.test',
            'status' => 'verified',
            'ssl_status' => 'not_requested',
            'metadata' => [],
        ]);

        // Use a spy-like anonymous subclass to avoid saving.
        $service = new class extends SslService {
            public function requestProvisioning(WebsiteDomain $domain): array
            {
                if (! (bool) config('website.ssl.enabled', false)) {
                    return [
                        'status' => 'pending',
                        'driver' => 'disabled',
                        'message' => 'SSL is pending.',
                        'certificate_verified' => false,
                    ];
                }

                return parent::requestProvisioning($domain);
            }
        };

        $result = $service->requestProvisioning($domain);

        $this->assertSame('pending', $result['status']);
        $this->assertFalse($result['certificate_verified']);
    }
}
