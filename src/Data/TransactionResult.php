<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Data;

use AhmadChebbo\AreebaPayment\Enums\GatewayResult;
use AhmadChebbo\AreebaPayment\Support\Amount;

/**
 * The outcome of a PAY, CAPTURE or REFUND. A decline is a normal result, not an exception:
 * check isSuccessful(), and $gatewayCode (APPROVED, DECLINED, INSUFFICIENT_FUNDS, ...) for why.
 */
final readonly class TransactionResult
{
    public function __construct(
        public GatewayResult $result,
        public ?string $transactionId,
        public ?string $type,
        public ?string $gatewayCode,
        public string $amount,
        public ?string $currency,
        public GatewayOrder $order,
        public array $raw = [],
    ) {}

    public static function fromArray(array $response): self
    {
        $transaction = $response['transaction'] ?? [];

        return new self(
            result: GatewayResult::tryFrom((string) ($response['result'] ?? '')) ?? GatewayResult::Unknown,
            transactionId: $transaction['id'] ?? null,
            type: $transaction['type'] ?? null,
            gatewayCode: $response['response']['gatewayCode'] ?? null,
            amount: Amount::fromGateway($transaction['amount'] ?? null),
            currency: $transaction['currency'] ?? null,
            order: GatewayOrder::fromArray($response['order'] ?? []),
            raw: $response,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->result === GatewayResult::Success;
    }
}
