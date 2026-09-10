<?php

namespace App\Enums\Finance;

/**
 * Future quote delivery states. Phase B does not persist or transition these.
 * Send / viewed tracking belongs to a later phase.
 */
enum QuoteDeliveryStatus: string
{
    case Sent = 'sent';
    case Viewed = 'viewed';
}
