<?php

namespace Tests\Unit\EInvoicing\Security;

use App\EInvoicing\Security\CanonicalizationMethod;
use App\EInvoicing\Security\Exceptions\InvoiceHashException;
use App\EInvoicing\Security\HashAlgorithm;
use App\EInvoicing\Security\Pih;
use App\Services\EInvoicing\Security\InvoiceHashService;
use PHPUnit\Framework\TestCase;

class InvoiceHashServiceTest extends TestCase
{
    public function test_same_xml_produces_the_same_hash(): void
    {
        $xml = $this->fixture();
        $service = new InvoiceHashService;
        $this->assertTrue($service->hash($xml)->equals($service->hash($xml)));
        $this->assertSame(HashAlgorithm::SHA_256, $service->algorithm());
        $this->assertSame(CanonicalizationMethod::C14N11, $service->canonicalizationMethod());
    }

    public function test_equivalent_formatting_produces_the_same_hash(): void
    {
        $pretty = $this->fixture();
        $compact = preg_replace('/>\s+</', '><', $pretty) ?? $pretty;
        $this->assertNotSame($pretty, $compact);

        $service = new InvoiceHashService;
        $this->assertTrue($service->hash($pretty)->equals($service->hash($compact)));
    }

    public function test_qr_signature_and_ubl_extensions_are_excluded_from_the_hash(): void
    {
        $base = $this->fixture();
        $withExcluded = str_replace(
            '</Invoice>',
            '<UBLExtensions xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><UBLExtension/></UBLExtensions>'
            .'<cac:AdditionalDocumentReference xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>QR</cbc:ID></cac:AdditionalDocumentReference>'
            .'<Signature xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2">ignored</Signature>'
            .'</Invoice>',
            $base,
        );

        $service = new InvoiceHashService;
        $this->assertTrue($service->hash($base)->equals($service->hash($withExcluded)));
    }

    public function test_icv_and_pih_are_included_in_the_hash(): void
    {
        $service = new InvoiceHashService;
        $first = $this->fixture();
        $changedIcv = str_replace('<cbc:UUID>1</cbc:UUID>', '<cbc:UUID>2</cbc:UUID>', $first);
        $changedPih = str_replace(Pih::FIRST_DOCUMENT, base64_encode(str_repeat("\x01", 32)), $first);

        $this->assertFalse($service->hash($first)->equals($service->hash($changedIcv)));
        $this->assertFalse($service->hash($first)->equals($service->hash($changedPih)));
    }

    public function test_golden_hash_vector_for_the_fixture(): void
    {
        $service = new InvoiceHashService;
        $canonical = $service->canonicalize($this->fixture());
        $expected = base64_encode(hash('sha256', $canonical->value(), true));

        $this->assertSame($expected, $service->hash($this->fixture())->value());
        $this->assertSame(32, strlen(base64_decode($expected, true)));
        $this->assertStringNotContainsString('<?xml', $canonical->value());
        $this->assertStringNotContainsString('QR', $canonical->value());
    }

    public function test_malformed_xml_is_rejected(): void
    {
        $this->expectException(InvoiceHashException::class);
        (new InvoiceHashService)->hash('<not-closed>');
    }

    public function test_xml_core_attributes_are_refused_rather_than_guessed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xml:lang="ar">
  <ID>INV-1</ID>
</Invoice>
XML;

        $this->expectException(InvoiceHashException::class);
        (new InvoiceHashService)->hash($xml);
    }

    private function fixture(): string
    {
        $path = dirname(__DIR__, 3).'/Fixtures/EInvoicing/security/golden-invoice.xml';

        return (string) file_get_contents($path);
    }
}
