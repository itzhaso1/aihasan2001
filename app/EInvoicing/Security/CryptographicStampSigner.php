<?php

namespace App\EInvoicing\Security;

/**
 * Production XAdES/ECDSA cryptographic stamping requires a CSID issued by
 * ZATCA. That belongs to a later certificate phase. This interface is the
 * local boundary only.
 */
interface CryptographicStampSigner
{
    public function isProductionIdentity(): bool;

    public function signCanonicalDigest(InvoiceHash $hash): CryptographicStampResult;
}
