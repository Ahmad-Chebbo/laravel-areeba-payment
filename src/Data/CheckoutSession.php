<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Data;

/**
 * A hosted checkout session. Store $successIndicator with your order: it is the
 * secret Areeba::verify() compares the returned resultIndicator against.
 *
 * Send the payer to the payment page with either <x-areeba::checkout :session="$session" />
 * or a plain redirect($session->paymentUrl), e.g. from an API or mobile app.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $sessionId,
        public string $successIndicator,
        public string $orderId,
        public ?string $paymentUrl = null,
        public array $raw = [],
    ) {}

    public static function fromResponse(array $response, string $orderId, ?string $paymentUrl = null): self
    {
        return new self(
            sessionId: $response['session']['id'],
            successIndicator: $response['successIndicator'],
            orderId: $orderId,
            paymentUrl: $paymentUrl,
            raw: $response,
        );
    }
}
