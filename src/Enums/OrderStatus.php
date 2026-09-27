<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Enums;

/**
 * The gateway's `order.status`: the state of the order as a whole.
 */
enum OrderStatus: string
{
    case Authenticated = 'AUTHENTICATED';
    case AuthenticationInitiated = 'AUTHENTICATION_INITIATED';
    case AuthenticationNotNeeded = 'AUTHENTICATION_NOT_NEEDED';
    case AuthenticationUnsuccessful = 'AUTHENTICATION_UNSUCCESSFUL';
    case Authorized = 'AUTHORIZED';
    case Cancelled = 'CANCELLED';
    case Captured = 'CAPTURED';
    case ChargebackProcessed = 'CHARGEBACK_PROCESSED';
    case Disputed = 'DISPUTED';
    case ExcessivelyRefunded = 'EXCESSIVELY_REFUNDED';
    case Failed = 'FAILED';
    case Funding = 'FUNDING';
    case Initiated = 'INITIATED';
    case PartiallyCaptured = 'PARTIALLY_CAPTURED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';
    case RefundRequested = 'REFUND_REQUESTED';
    case Verified = 'VERIFIED';

    /** The money was taken (a partial refund since does not undo that). */
    public function isPaid(): bool
    {
        return in_array($this, [self::Captured, self::PartiallyRefunded], true);
    }

    /** The amount is held and waiting for Areeba::capture(). */
    public function isAuthorized(): bool
    {
        return $this === self::Authorized;
    }
}
