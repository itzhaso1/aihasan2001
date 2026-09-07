<?php

namespace Tests\Unit;

use App\Services\Domain\DomainName;
use RuntimeException;
use Tests\TestCase;

class DomainNameTest extends TestCase
{
    public function test_normalize_strips_protocol_prefix_and_common_host_noise(): void
    {
        $normalized = DomainName::normalize('https://WWW.Example.COM:443/path?x=1');

        $this->assertSame('example.com', $normalized);
    }

    public function test_from_input_extracts_sld_and_tld(): void
    {
        // DomainName treats the first label as SLD and the remainder as TLD
        // so multi-label hosts like clinic.platform.test => sld=clinic, tld=platform.test
        $domain = DomainName::fromInput('clinic.platform.test');

        $this->assertSame('clinic.platform.test', $domain->domain);
        $this->assertSame('clinic', $domain->sld);
        $this->assertSame('platform.test', $domain->tld);
    }

    public function test_from_input_supports_second_level_public_suffixes(): void
    {
        $domain = DomainName::fromInput('shop.example.co.uk');

        $this->assertSame('shop', $domain->sld);
        $this->assertSame('example.co.uk', $domain->tld);
    }

    public function test_from_input_rejects_invalid_domain_strings(): void
    {
        $this->expectException(RuntimeException::class);
        DomainName::fromInput('invalid-domain');
    }
}
