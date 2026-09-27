<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Data;

use AhmadChebbo\AreebaPayment\Enums\OrderStatus;
use AhmadChebbo\AreebaPayment\Support\Amount;

/**
 * An order as the gateway sees it. $status is null for a status this package does not know yet.
 */
final readonly class GatewayOrder
{
    public function __construct(
        public string $id,
        public ?OrderStatus $status,
        public string $amount,
        public ?string $currency,
        public string $totalRefundedAmount,
        public array $raw = [],
    ) {}

    public static function fromArray(array $order): self
    {
        return new self(
            id: (string) ($order['id'] ?? ''),
            status: OrderStatus::tryFrom((string) ($order['status'] ?? '')),
            amount: Amount::fromGateway($order['amount'] ?? null),
            currency: $order['currency'] ?? null,
            totalRefundedAmount: Amount::fromGateway($order['totalRefundedAmount'] ?? 0),
            raw: $order,
        );
    }

    public function isPaid(): bool
    {
        return $this->status?->isPaid() ?? false;
    }

    public function isAuthorized(): bool
    {
        return $this->status?->isAuthorized() ?? false;
    }
}
