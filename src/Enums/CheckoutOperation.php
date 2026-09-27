<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Enums;

/**
 * What the hosted payment page does with the card (`interaction.operation`).
 */
enum CheckoutOperation: string
{
    /** Charge the card now. */
    case Purchase = 'PURCHASE';

    /** Hold the amount; take it later with Areeba::capture(). */
    case Authorize = 'AUTHORIZE';

    /** Check the card without charging it, e.g. before Areeba::tokenize(). */
    case Verify = 'VERIFY';

    /** Collect card details only. */
    case None = 'NONE';
}
