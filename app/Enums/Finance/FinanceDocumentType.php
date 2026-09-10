<?php

namespace App\Enums\Finance;

/**
 * Finance documents that can share finance_document_deliveries.
 * Quote and invoice email sends persist here; receipt remains reserved.
 */
enum FinanceDocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
}
