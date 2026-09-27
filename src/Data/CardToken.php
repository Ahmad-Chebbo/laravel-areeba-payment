<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Data;

/**
 * A card stored on the gateway. Save $token (and the display fields) in your own table;
 * the full card number never reaches your server.
 */
final readonly class CardToken
{
    public function __construct(
        public string $token,
        public ?string $status,
        public ?string $maskedNumber,
        public ?string $lastFour,
        public ?string $expiryMonth,
        public ?string $expiryYear,
        public ?string $scheme,
        public ?string $nameOnCard,
        public array $raw = [],
    ) {}

    public static function fromArray(array $response): self
    {
        $card = $response['sourceOfFunds']['provided']['card'] ?? [];
        $number = $card['number'] ?? null;
        $expiry = $card['expiry'] ?? null; // MMYY

        return new self(
            token: $response['token'],
            status: $response['status'] ?? null,
            maskedNumber: $number,
            lastFour: $number ? substr($number, -4) : null,
            expiryMonth: $expiry ? substr($expiry, 0, 2) : null,
            expiryYear: $expiry ? substr($expiry, 2, 2) : null,
            scheme: $card['scheme'] ?? null,
            nameOnCard: $card['nameOnCard'] ?? null,
            raw: $response,
        );
    }

    public function isValid(): bool
    {
        return $this->status === 'VALID';
    }
}
