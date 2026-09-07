<?php

namespace Tests\Unit\Website;

use App\Services\Domain\DomainName;
use PHPUnit\Framework\TestCase;

class DomainNameNormalizationTest extends TestCase
{
    public function test_normalize_removes_protocol_www_port_and_trailing_slash(): void
    {
        $normalized = DomainName::normalize('https://WWW.Example-Clinic.COM:443/path/');

        $this->assertSame('example-clinic.com', $normalized);
    }

    public function test_from_input_splits_sld_and_tld(): void
    {
        $domain = DomainName::fromInput('AIDA-CLINIC.com');

        $this->assertSame('aida-clinic.com', $domain->domain);
        $this->assertSame('aida-clinic', $domain->sld);
        $this->assertSame('com', $domain->tld);
    }
}
