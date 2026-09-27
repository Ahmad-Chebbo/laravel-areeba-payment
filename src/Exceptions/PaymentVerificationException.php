<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Exceptions;

/**
 * The return from the hosted payment page could not be trusted. Do not mark the order paid.
 */
class PaymentVerificationException extends AreebaException
{
    public static function indicatorMismatch(string $orderId): self
    {
        return new self("The result indicator for order [{$orderId}] does not match its success indicator.", ['order_id' => $orderId]);
    }

    public static function mismatch(string $orderId, string $field, string $expected, string $actual): self
    {
        return new self(
            "The gateway {$field} for order [{$orderId}] is [{$actual}], expected [{$expected}].",
            ['order_id' => $orderId, 'field' => $field, 'expected' => $expected, 'actual' => $actual],
        );
    }
}
