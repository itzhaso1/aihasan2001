<?php

namespace Tests\Unit\EInvoicing\Security;

use App\EInvoicing\EInvoiceAddress;
use App\EInvoicing\EInvoiceDocument;
use App\EInvoicing\EInvoiceLine;
use App\EInvoicing\EInvoiceParty;
use App\EInvoicing\EInvoiceTax;
use App\EInvoicing\EInvoiceTotals;
use App\EInvoicing\InvoiceType;
use App\EInvoicing\Security\Icv;
use App\EInvoicing\Security\Pih;
use App\EInvoicing\Xml\UblMapper;
use App\Enums\EInvoicing\ComplianceStatus;
use App\Enums\EInvoicing\ElectronicDocumentKind;
use App\Enums\EInvoicing\ElectronicTaxClassification;
use App\Enums\EInvoicing\InvoiceTransactionCode;
use App\Enums\EInvoicing\InvoiceTypeCode;
use App\Models\Finance\IssuedDocumentSnapshot;
use App\Services\EInvoicing\EInvoiceXmlGenerator;
use App\Services\EInvoicing\Security\SecurityXmlEnricher;
use PHPUnit\Framework\TestCase;

class SecurityXmlEnricherTest extends TestCase
{
    public function test_icv_and_pih_are_injected_before_the_supplier_party(): void
    {
        $xml = (new EInvoiceXmlGenerator(new UblMapper))->generate($this->document());
        $enriched = (new SecurityXmlEnricher)->enrich($xml, Icv::fromInt(7), Pih::firstDocument());

        $this->assertMatchesRegularExpression(
            '/<cac:AdditionalDocumentReference>\s*<cbc:ID>ICV<\/cbc:ID>\s*<cbc:UUID>7<\/cbc:UUID>/',
            $enriched,
        );
        $this->assertStringContainsString(Pih::FIRST_DOCUMENT, $enriched);
        $this->assertTrue(
            strpos($enriched, 'cbc:ID>ICV') < strpos($enriched, 'AccountingSupplierParty')
        );
        $this->assertStringNotContainsString('<cbc:ID>QR</cbc:ID>', $enriched);
    }

    private function document(): EInvoiceDocument
    {
        return new EInvoiceDocument(
            workspaceId: 1,
            sourceSnapshotId: 99,
            sourceType: IssuedDocumentSnapshot::SOURCE_FINANCE_INVOICE,
            sourceId: 7,
            invoiceType: new InvoiceType(
                ElectronicDocumentKind::TaxInvoice,
                InvoiceTypeCode::TaxInvoice,
                InvoiceTransactionCode::Standard,
            ),
            documentNumber: 'INV-100',
            issueDate: '2026-09-01',
            issuedAt: '2026-09-01T10:15:30+03:00',
            currency: 'SAR',
            seller: new EInvoiceParty(
                'company',
                'Issued Co',
                '310000000000003',
                '1010000000',
                new EInvoiceAddress(null, '1234', 'King Fahd Road', 'Al Olaya', 'Riyadh', '12345', 'SA', '5678'),
                '0111111111',
                'seller@example.com',
            ),
            buyer: new EInvoiceParty(
                'customer',
                'E-Invoice Buyer',
                '300111111111113',
                null,
                new EInvoiceAddress(null, null, 'Buyer Street', 'Al Balad', 'Jeddah', '22222', 'SA', null),
                null,
                null,
            ),
            lines: [new EInvoiceLine(
                'خدمة فوترة',
                'خدمة فوترة',
                '1.000',
                '100.00',
                '0.00',
                '100.00',
                ElectronicTaxClassification::Standard,
                '15.00',
                '15.00',
                '115.00',
                null,
            )],
            tax: new EInvoiceTax(ElectronicTaxClassification::Standard, '15.00', '15.00', 'exclusive', null),
            totals: new EInvoiceTotals('100.00', '0.00', '100.00', '15.00', '115.00', '0.00', '115.00'),
            originalDocument: null,
            businessStatus: 'issued',
            paymentStatus: 'unpaid',
            complianceStatus: ComplianceStatus::Ready,
            payment: [],
            sourceMetadata: [],
        );
    }
}
