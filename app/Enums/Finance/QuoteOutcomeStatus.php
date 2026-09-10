<?php

namespace App\Enums\Finance;

/**
 * Future commercial outcomes. Phase B does not persist or transition these.
 * Accept / reject / expire / convert belong to later phases.
 */
enum QuoteOutcomeStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';
}
