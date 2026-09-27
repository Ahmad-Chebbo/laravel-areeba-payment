<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Data;

use AhmadChebbo\AreebaPayment\Enums\GatewayResult;

/**
 * A webhook notification. The gateway sends the same body as the API response of the
 * transaction that changed the order, so $transaction is that transaction.
 */
final readonly class Notification
{
    public function __construct(
        public ?string $id,
        public int $attempt,
        public GatewayOrder $order,
        public ?TransactionResult $transaction,
        public array $raw = [],
    ) {}

    public static function fromPayload(array $payload, ?string $id, int $attempt): self
    {
        return new self(
            id: $id ?: null,
            attempt: $attempt,
            order: GatewayOrder::fromArray($payload['order'] ?? []),
            transaction: isset($payload['transaction']) ? TransactionResult::fromArray($payload) : null,
            raw: $payload,
        );
    }

    public function isRefund(): bool
    {
        return $this->isRefundTransaction() && $this->transaction->isSuccessful();
    }

    public function isPaymentSuccess(): bool
    {
        return ! $this->isRefundTransaction() && $this->transaction?->isSuccessful() && $this->order->isPaid();
    }

    public function isPaymentFailure(): bool
    {
        return ! $this->isRefundTransaction() && $this->transaction?->result === GatewayResult::Failure;
    }

    private function isRefundTransaction(): bool
    {
        return $this->transaction?->type === 'REFUND';
    }
}
