<?php

namespace Tests\Unit\Domain;

use App\Services\Domain\DomainName;
use App\Services\Domain\DomainProviderException;
use App\Services\Domain\NamecheapRegistrar;
use App\Services\Domain\NamecheapXmlParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NamecheapRegistrarTest extends TestCase
{
    public function test_domain_name_normalization_and_parts_support_multi_segment_tld(): void
    {
        $normalized = DomainName::normalize('https://WWW.Example.CO.UK/path');
        $this->assertSame('example.co.uk', $normalized);

        $parts = DomainName::fromInput('example.co.uk');
        $this->assertSame('example', $parts->sld);
        $this->assertSame('co.uk', $parts->tld);
    }

    public function test_check_availability_parses_namecheap_xml_response(): void
    {
        config()->set('services.namecheap', [
            'env' => 'sandbox',
            'api_user' => 'user',
            'api_key' => 'key',
            'username' => 'user',
            'client_ip' => '127.0.0.1',
            'timeout' => 20,
            'connect_timeout' => 8,
            'base_url_sandbox' => 'https://api.sandbox.namecheap.com/xml.response',
            'base_url_production' => 'https://api.namecheap.com/xml.response',
        ]);

        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?>'.
                '<ApiResponse xmlns="http://api.namecheap.com/xml.response" Status="OK">'.
                '<Errors/><Warnings/><RequestedCommand>namecheap.domains.check</RequestedCommand>'.
                '<CommandResponse Type="namecheap.domains.check">'.
                '<DomainCheckResult Domain="example.com" Available="true" ErrorNo="0" Description="" IsPremiumName="false" PremiumRegistrationPrice="0" PremiumRenewalPrice="0" PremiumRestorePrice="0" PremiumTransferPrice="0" IcannFee="0.18" EapFee="0"/>'.
                '</CommandResponse></ApiResponse>',
                200
            ),
        ]);

        $registrar = new NamecheapRegistrar(new NamecheapXmlParser());
        $result = $registrar->checkAvailability(['example.com']);

        $this->assertCount(1, $result);
        $this->assertSame('example.com', $result[0]['domain']);
        $this->assertTrue($result[0]['available']);
    }

    public function test_check_availability_throws_provider_exception_when_api_status_error(): void
    {
        config()->set('services.namecheap', [
            'env' => 'sandbox',
            'api_user' => 'user',
            'api_key' => 'key',
            'username' => 'user',
            'client_ip' => '127.0.0.1',
            'timeout' => 20,
            'connect_timeout' => 8,
            'base_url_sandbox' => 'https://api.sandbox.namecheap.com/xml.response',
            'base_url_production' => 'https://api.namecheap.com/xml.response',
        ]);

        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?>'.
                '<ApiResponse xmlns="http://api.namecheap.com/xml.response" Status="ERROR">'.
                '<Errors><Error Number="1017105">Parameter ClientIP is disabled or locked</Error></Errors>'.
                '<RequestedCommand>namecheap.domains.check</RequestedCommand>'.
                '</ApiResponse>',
                200
            ),
        ]);

        $this->expectException(DomainProviderException::class);

        $registrar = new NamecheapRegistrar(new NamecheapXmlParser());
        $registrar->checkAvailability(['example.com']);
    }
}
