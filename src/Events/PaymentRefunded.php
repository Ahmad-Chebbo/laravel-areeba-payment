<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Events;

/**
 * A refund on the order succeeded (check $notification->order->status for full vs partial).
 */
class PaymentRefunded extends NotificationEvent
{
}
