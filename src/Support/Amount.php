<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Support;

use AhmadChebbo\AreebaPayment\Exceptions\AreebaException;

/**
 * Amounts travel as decimal strings in major units ('25.50'), never floats,
 * so no rounding happens on the way to the gateway.
 */
final class Amount
{
    /** @throws AreebaException for anything but a positive decimal with up to 3 places */
    public static function format(string|int $amount): string
    {
        $value = (string) $amount;

        if (! preg_match('/^\d{1,12}(\.\d{1,3})?$/', $value) || (float) $value <= 0) {
            throw new AreebaException("Invalid amount [{$value}]. Use a positive decimal string such as '25.50'.");
        }

        return $value;
    }

    /** The gateway returns amounts as JSON numbers; keep at least two decimal places. */
    public static function fromGateway(int|float|string|null $amount): string
    {
        if ($amount === null || is_string($amount)) {
            return (string) $amount;
        }

        $decimals = strlen(substr(strrchr((string) $amount, '.') ?: '', 1));

        return number_format((float) $amount, max(2, $decimals), '.', '');
    }

    public static function equals(string $a, string $b): bool
    {
        return round((float) $a * 1000) === round((float) $b * 1000);
    }
}
