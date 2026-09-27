<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Services;

use AhmadChebbo\AreebaPayment\Data\CardToken;
use AhmadChebbo\AreebaPayment\Data\CheckoutSession;
use AhmadChebbo\AreebaPayment\Data\GatewayOrder;
use AhmadChebbo\AreebaPayment\Data\TransactionResult;
use AhmadChebbo\AreebaPayment\Enums\CheckoutOperation;
use AhmadChebbo\AreebaPayment\Exceptions\AreebaException;
use AhmadChebbo\AreebaPayment\Exceptions\GatewayException;
use AhmadChebbo\AreebaPayment\Exceptions\PaymentVerificationException;
use AhmadChebbo\AreebaPayment\Support\Amount;
use AhmadChebbo\AreebaPayment\Support\AreebaClient;
use Closure;
use Illuminate\Support\Str;

/**
 * The Areeba (Mastercard Gateway) operations a shop needs. Every method returns data;
 * storing it against your own orders and users is up to the application.
 *
 * Order IDs are yours: pass the same ID to checkout(), verify(), refund() and so on.
 * They must be unique per merchant, 1–40 characters.
 */
class AreebaGateway
{
    /** The hosted payment page version used for the direct payment link. */
    private const CHECKOUT_VERSION = '1.0.0';

    /**
     * @param  Closure(): ?string  $notificationUrl  the webhook URL, resolved when a checkout starts
     */
    public function __construct(
        private readonly AreebaClient $client,
        private readonly string $merchantName,
        private readonly string $defaultCurrency,
        private readonly ?Closure $notificationUrl = null,
    ) {}

    /**
     * Start a hosted checkout. Render the session with <x-areeba::checkout :session="$session" />
     * or redirect to $session->paymentUrl; the payer comes back to $returnUrl with
     * ?resultIndicator=... for verify(), to $cancelUrl on cancel, and to $timeoutUrl
     * if the session expires.
     *
     * @param  array  $extra  any other INITIATE_CHECKOUT fields, merged over the defaults
     *                        (e.g. interaction.displayControl, customer, billing)
     *
     * @throws AreebaException|GatewayException
     */
    public function checkout(
        string $orderId,
        string|int $amount,
        string $returnUrl,
        ?string $currency = null,
        ?string $description = null,
        ?string $cancelUrl = null,
        ?string $timeoutUrl = null,
        CheckoutOperation $operation = CheckoutOperation::Purchase,
        array $extra = [],
    ): CheckoutSession {
        $payload = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'interaction' => array_filter([
                'operation' => $operation->value,
                'merchant' => ['name' => $this->merchantName],
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'timeoutUrl' => $timeoutUrl,
            ]),
            'order' => array_filter([
                'id' => $orderId,
                'amount' => Amount::format($amount),
                'currency' => $currency ?? $this->defaultCurrency,
                'description' => $description,
                'notificationUrl' => $this->secureNotificationUrl(),
            ]),
        ];

        $response = $this->client->post('session', array_replace_recursive($payload, $extra));

        return CheckoutSession::fromResponse($response, $orderId, $this->paymentUrl($response['session']['id']));
    }

    /**
     * Confirm a payer's return from the hosted page before trusting it.
     *
     * Checks the returned resultIndicator against the successIndicator you stored at checkout,
     * then reads the order from the gateway. Check isPaid() (or isAuthorized()) on the result.
     *
     * @throws PaymentVerificationException when the return or the order does not match
     * @throws GatewayException
     */
    public function verify(
        string $orderId,
        string $resultIndicator,
        string $successIndicator,
        string|int|null $expectedAmount = null,
        ?string $expectedCurrency = null,
    ): GatewayOrder {
        if ($resultIndicator === '' || ! hash_equals($successIndicator, $resultIndicator)) {
            throw PaymentVerificationException::indicatorMismatch($orderId);
        }

        $order = $this->retrieveOrder($orderId);

        if ($expectedAmount !== null && ! Amount::equals($order->amount, Amount::format($expectedAmount))) {
            throw PaymentVerificationException::mismatch($orderId, 'amount', (string) $expectedAmount, $order->amount);
        }

        if ($expectedCurrency !== null && strcasecmp($expectedCurrency, (string) $order->currency) !== 0) {
            throw PaymentVerificationException::mismatch($orderId, 'currency', $expectedCurrency, (string) $order->currency);
        }

        return $order;
    }

    /** @throws GatewayException */
    public function retrieveOrder(string $orderId): GatewayOrder
    {
        return GatewayOrder::fromArray($this->client->get('order/'.rawurlencode($orderId)));
    }

    /**
     * Refund all or part of a paid order. Partial refunds may be repeated up to the paid amount.
     *
     * @throws AreebaException|GatewayException
     */
    public function refund(string $orderId, string|int $amount, ?string $currency = null, ?string $transactionId = null): TransactionResult
    {
        return $this->transaction($orderId, $transactionId, [
            'apiOperation' => 'REFUND',
            'transaction' => $this->money($amount, $currency),
        ]);
    }

    /**
     * Take an amount held by a CheckoutOperation::Authorize checkout.
     *
     * @throws AreebaException|GatewayException
     */
    public function capture(string $orderId, string|int $amount, ?string $currency = null, ?string $transactionId = null): TransactionResult
    {
        return $this->transaction($orderId, $transactionId, [
            'apiOperation' => 'CAPTURE',
            'transaction' => $this->money($amount, $currency),
        ]);
    }

    /**
     * Charge a stored card, e.g. a subscription renewal. For recurring or unscheduled charges
     * pass the agreement fields your Areeba account requires in $extra
     * (e.g. ['agreement' => ['id' => ..., 'type' => 'RECURRING']]).
     *
     * @throws AreebaException|GatewayException
     */
    public function payWithToken(
        string $orderId,
        string|int $amount,
        string $token,
        ?string $currency = null,
        ?string $transactionId = null,
        array $extra = [],
    ): TransactionResult {
        return $this->transaction($orderId, $transactionId, array_replace_recursive([
            'apiOperation' => 'PAY',
            'order' => $this->money($amount, $currency),
            'sourceOfFunds' => ['type' => 'CARD', 'token' => $token],
        ], $extra));
    }

    /**
     * Store the card the payer entered in a checkout session (use CheckoutOperation::Verify
     * to save a card without charging it).
     *
     * @throws GatewayException
     */
    public function tokenize(string $sessionId): CardToken
    {
        return CardToken::fromArray($this->client->post('token', [
            'session' => ['id' => $sessionId],
            'sourceOfFunds' => ['type' => 'CARD'],
        ]));
    }

    /** @throws GatewayException */
    public function retrieveToken(string $token): CardToken
    {
        return CardToken::fromArray($this->client->get('token/'.rawurlencode($token)));
    }

    /** @throws GatewayException */
    public function deleteToken(string $token): void
    {
        $this->client->delete('token/'.rawurlencode($token));
    }

    private function transaction(string $orderId, ?string $transactionId, array $payload): TransactionResult
    {
        $path = sprintf('order/%s/transaction/%s', rawurlencode($orderId), rawurlencode($transactionId ?? (string) Str::uuid()));

        return TransactionResult::fromArray($this->client->put($path, $payload));
    }

    private function paymentUrl(string $sessionId): string
    {
        return sprintf('%s/checkout/pay/%s?checkoutVersion=%s', $this->client->gatewayUrl(), rawurlencode($sessionId), self::CHECKOUT_VERSION);
    }

    /** @return array{amount: string, currency: string} */
    private function money(string|int $amount, ?string $currency): array
    {
        return ['amount' => Amount::format($amount), 'currency' => $currency ?? $this->defaultCurrency];
    }

    /** The gateway only accepts an https notification URL, so none is sent from a local http setup. */
    private function secureNotificationUrl(): ?string
    {
        $url = $this->notificationUrl ? ($this->notificationUrl)() : null;

        return $url && str_starts_with($url, 'https://') ? $url : null;
    }
}
