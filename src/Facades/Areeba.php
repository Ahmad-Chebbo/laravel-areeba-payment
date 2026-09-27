<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Facades;

use AhmadChebbo\AreebaPayment\Services\AreebaGateway;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AhmadChebbo\AreebaPayment\Data\CheckoutSession checkout(string $orderId, string|int $amount, string $returnUrl, ?string $currency = null, ?string $description = null, ?string $cancelUrl = null, ?string $timeoutUrl = null, \AhmadChebbo\AreebaPayment\Enums\CheckoutOperation $operation = \AhmadChebbo\AreebaPayment\Enums\CheckoutOperation::Purchase, array $extra = [])
 * @method static \AhmadChebbo\AreebaPayment\Data\GatewayOrder verify(string $orderId, string $resultIndicator, string $successIndicator, string|int|null $expectedAmount = null, ?string $expectedCurrency = null)
 * @method static \AhmadChebbo\AreebaPayment\Data\GatewayOrder retrieveOrder(string $orderId)
 * @method static \AhmadChebbo\AreebaPayment\Data\TransactionResult refund(string $orderId, string|int $amount, ?string $currency = null, ?string $transactionId = null)
 * @method static \AhmadChebbo\AreebaPayment\Data\TransactionResult capture(string $orderId, string|int $amount, ?string $currency = null, ?string $transactionId = null)
 * @method static \AhmadChebbo\AreebaPayment\Data\TransactionResult payWithToken(string $orderId, string|int $amount, string $token, ?string $currency = null, ?string $transactionId = null, array $extra = [])
 * @method static \AhmadChebbo\AreebaPayment\Data\CardToken tokenize(string $sessionId)
 * @method static \AhmadChebbo\AreebaPayment\Data\CardToken retrieveToken(string $token)
 * @method static void deleteToken(string $token)
 *
 * @see AreebaGateway
 */
class Areeba extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AreebaGateway::class;
    }
}
