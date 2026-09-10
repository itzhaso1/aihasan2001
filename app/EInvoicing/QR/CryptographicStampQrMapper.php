<?php

namespace App\EInvoicing\QR;

use App\EInvoicing\Security\CryptographicStamp;
use App\EInvoicing\Security\Exceptions\CryptographicStampException;

/**
 * Maps a local cryptographic stamp onto QR Tags 7–8.
 *
 * Tag 7: UTF-8 Base64 of the ECDSA DER signature (Detailed Technical
 * Guidelines section 6.2 example matching SignatureValue).
 * Tag 8: raw SubjectPublicKeyInfo DER (Detailed Technical Guidelines
 * section 6 hex dump).
 * Tag 9: never generated. Accepted only as an externally provisioned artifact.
 */
final class CryptographicStampQrMapper
{
    public function fields(
        CryptographicStamp $stamp,
        ?string $externallyProvisionedZatcaCaSignature = null,
    ): CryptographicQrFields {
        if ($externallyProvisionedZatcaCaSignature !== null && $externallyProvisionedZatcaCaSignature === '') {
            throw new CryptographicStampException(
                'Tag 9 cannot be an empty placeholder.',
                operation: 'qr_map',
                reason: 'empty_tag_9',
            );
        }

        return new CryptographicQrFields(
            ecdsaSignature: $stamp->signatureDerBase64,
            ecdsaPublicKey: $stamp->publicKeySpkiDer,
            zatcaCaSignature: $externallyProvisionedZatcaCaSignature,
        );
    }
}
