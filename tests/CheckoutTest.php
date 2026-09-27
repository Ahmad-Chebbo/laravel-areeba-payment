<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Enums\CheckoutOperation;
use AhmadChebbo\AreebaPayment\Exceptions\AreebaException;
use AhmadChebbo\AreebaPayment\Exceptions\GatewayException;
use AhmadChebbo\AreebaPayment\Facades\Areeba;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class CheckoutTest extends TestCase
{
    private function fakeSession(): void
    {
        Http::fake([
            self::API.'/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => ['id' => 'SESSION0002', 'updateStatus' => 'SUCCESS'],
                'successIndicator' => 'indicator-abc',
            ]),
        ]);
    }

    public function test_checkout_initiates_a_session_and_returns_its_details(): void
    {
        $this->fakeSession();

        $session = Areeba::checkout(orderId: '1001', amount: '25.50', returnUrl: 'https://shop.test/return');

        $this->assertSame('SESSION0002', $session->sessionId);
        $this->assertSame('indicator-abc', $session->successIndicator);
        $this->assertSame('1001', $session->orderId);
    }

    public function test_checkout_session_links_to_the_hosted_payment_page(): void
    {
        $this->fakeSession();

        $session = Areeba::checkout(orderId: '1001', amount: '25.50', returnUrl: 'https://shop.test/return');

        $this->assertSame('https://areeba.test/checkout/pay/SESSION0002?checkoutVersion=1.0.0', $session->paymentUrl);
    }

    public function test_checkout_sends_a_timeout_url_when_given(): void
    {
        $this->fakeSession();

        Areeba::checkout(orderId: '1001', amount: '25.50', returnUrl: 'https://shop.test/return', timeoutUrl: 'https://shop.test/expired');

        Http::assertSent(fn (Request $request) => $request['interaction']['timeoutUrl'] === 'https://shop.test/expired');
    }

    public function test_checkout_sends_the_initiate_checkout_payload_with_basic_auth(): void
    {
        $this->fakeSession();

        Areeba::checkout(
            orderId: '1001',
            amount: '25.50',
            returnUrl: 'https://shop.test/return',
            description: 'Order #1001',
            cancelUrl: 'https://shop.test/cart',
        );

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === self::API.'/session'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant.TEST123:api-password'))
                && $request['apiOperation'] === 'INITIATE_CHECKOUT'
                && $request['interaction']['operation'] === 'PURCHASE'
                && $request['interaction']['merchant']['name'] === 'Test Shop'
                && $request['interaction']['returnUrl'] === 'https://shop.test/return'
                && $request['interaction']['cancelUrl'] === 'https://shop.test/cart'
                && $request['order'] === [
                    'id' => '1001',
                    'amount' => '25.50',
                    'currency' => 'USD',
                    'description' => 'Order #1001',
                ];
        });
    }

    public function test_checkout_accepts_an_operation_currency_and_extra_fields(): void
    {
        $this->fakeSession();

        Areeba::checkout(
            orderId: '1001',
            amount: 100,
            returnUrl: 'https://shop.test/return',
            currency: 'LBP',
            operation: CheckoutOperation::Authorize,
            extra: ['interaction' => ['displayControl' => ['billingAddress' => 'HIDE']]],
        );

        Http::assertSent(fn (Request $request) => $request['interaction']['operation'] === 'AUTHORIZE'
            && $request['interaction']['displayControl'] === ['billingAddress' => 'HIDE']
            && $request['interaction']['merchant']['name'] === 'Test Shop'
            && $request['order']['amount'] === '100'
            && $request['order']['currency'] === 'LBP');
    }

    public function test_checkout_registers_the_webhook_url_when_it_is_https(): void
    {
        $this->fakeSession();
        config(['app.url' => 'https://shop.test']);
        $this->app['url']->forceRootUrl('https://shop.test');

        Areeba::checkout(orderId: '1001', amount: '10.00', returnUrl: 'https://shop.test/return');

        Http::assertSent(fn (Request $request) => $request['order']['notificationUrl'] === 'https://shop.test/areeba/webhook');
    }

    public function test_checkout_skips_the_webhook_url_when_it_is_not_https(): void
    {
        $this->fakeSession();
        $this->app['url']->forceRootUrl('http://localhost');

        Areeba::checkout(orderId: '1001', amount: '10.00', returnUrl: 'http://localhost/return');

        Http::assertSent(fn (Request $request) => ! isset($request['order']['notificationUrl']));
    }

    public function test_checkout_rejects_an_invalid_amount_without_calling_the_gateway(): void
    {
        Http::fake();

        $this->expectException(AreebaException::class);

        try {
            Areeba::checkout(orderId: '1001', amount: '-5', returnUrl: 'https://shop.test/return');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_gateway_errors_are_thrown_with_the_gateway_explanation(): void
    {
        Http::fake([
            self::API.'/session' => Http::response([
                'result' => 'ERROR',
                'error' => ['cause' => 'INVALID_REQUEST', 'explanation' => 'Value for order.currency is invalid'],
            ], 400),
        ]);

        try {
            Areeba::checkout(orderId: '1001', amount: '10.00', returnUrl: 'https://shop.test/return');
            $this->fail('Expected a GatewayException.');
        } catch (GatewayException $e) {
            $this->assertSame('Value for order.currency is invalid', $e->getMessage());
            $this->assertSame('INVALID_REQUEST', $e->context()['cause']);
            $this->assertSame(400, $e->context()['status']);
        }
    }

    public function test_missing_credentials_are_reported_by_environment_variable_name(): void
    {
        config(['areeba.merchant_id' => null, 'areeba.api_password' => '']);
        Http::fake();

        $this->expectException(AreebaException::class);
        $this->expectExceptionMessage('AREEBA_MERCHANT_ID, AREEBA_API_PASSWORD');

        Areeba::checkout(orderId: '1001', amount: '10.00', returnUrl: 'https://shop.test/return');
    }
}
