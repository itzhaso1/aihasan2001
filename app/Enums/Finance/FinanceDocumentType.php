<?php

namespace App\Enums\Finance;

/**
 * Finance documents that can later share finance_document_deliveries.
 * Phase C persists quote deliveries only.
 */
enum FinanceDocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
}
