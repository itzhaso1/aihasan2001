<?php

namespace Tests\Unit\Domain;

use App\Services\Domain\NamecheapRegistrar;
use App\Services\Domain\NamecheapXmlParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NamecheapPricingTest extends TestCase
{
    public function test_get_tld_pricing_parses_register_renew_and_transfer(): void
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
                '<Errors/><RequestedCommand>namecheap.users.getPricing</RequestedCommand>'.
                '<CommandResponse Type="namecheap.users.getPricing">'.
                '<UserGetPricingResult>'.
                '<ProductType Name="DOMAIN">'.
                '<ProductCategory Name="REGISTER">'.
                '<Product Name="com"><Price Duration="1" DurationType="YEAR" Price="10.00" RegularPrice="12.00" YourPrice="9.50" Currency="USD"/></Product>'.
                '</ProductCategory>'.
                '<ProductCategory Name="RENEW">'.
                '<Product Name="com"><Price Duration="1" DurationType="YEAR" Price="11.00" RegularPrice="12.00" YourPrice="11.00" Currency="USD"/></Product>'.
                '</ProductCategory>'.
                '<ProductCategory Name="TRANSFER">'.
                '<Product Name="com"><Price Duration="1" DurationType="YEAR" Price="9.00" RegularPrice="9.00" YourPrice="9.00" Currency="USD"/></Product>'.
                '</ProductCategory>'.
                '</ProductType>'.
                '</UserGetPricingResult>'.
                '</CommandResponse></ApiResponse>',
                200
            ),
        ]);

        $registrar = new NamecheapRegistrar(new NamecheapXmlParser());
        $pricing = $registrar->getTldPricing(['com'], 1);

        $this->assertSame(9.5, $pricing['com']['registration']);
        $this->assertSame(11.0, $pricing['com']['renewal']);
        $this->assertSame(9.0, $pricing['com']['transfer']);
        $this->assertSame('USD', $pricing['com']['currency']);
    }
}
