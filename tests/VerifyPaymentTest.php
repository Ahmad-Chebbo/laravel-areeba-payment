<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Enums\OrderStatus;
use AhmadChebbo\AreebaPayment\Exceptions\PaymentVerificationException;
use AhmadChebbo\AreebaPayment\Facades\Areeba;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class VerifyPaymentTest extends TestCase
{
    private function fakeOrder(string $status = 'CAPTURED', string $amount = '25.50'): void
    {
        Http::fake([
            self::API.'/order/1001' => Http::response([
                'result' => 'SUCCESS',
                'id' => '1001',
                'status' => $status,
                'amount' => (float) $amount,
                'currency' => 'USD',
                'totalCapturedAmount' => (float) $amount,
                'totalRefundedAmount' => 0,
            ]),
        ]);
    }

    public function test_a_matching_result_indicator_returns_the_confirmed_order(): void
    {
        $this->fakeOrder();

        $order = Areeba::verify('1001', resultIndicator: 'indicator-abc', successIndicator: 'indicator-abc');

        $this->assertSame('1001', $order->id);
        $this->assertSame(OrderStatus::Captured, $order->status);
        $this->assertTrue($order->isPaid());
        $this->assertSame('25.50', $order->amount);
        $this->assertSame('USD', $order->currency);
    }

    public function test_a_mismatched_result_indicator_is_rejected_without_calling_the_gateway(): void
    {
        Http::fake();

        $this->expectException(PaymentVerificationException::class);

        try {
            Areeba::verify('1001', resultIndicator: 'forged', successIndicator: 'indicator-abc');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_an_amount_that_differs_from_the_expected_amount_is_rejected(): void
    {
        $this->fakeOrder(amount: '1.00');

        $this->expectException(PaymentVerificationException::class);
        $this->expectExceptionMessage('amount');

        Areeba::verify('1001', 'indicator-abc', 'indicator-abc', expectedAmount: '25.50');
    }

    public function test_an_unpaid_order_is_returned_so_the_caller_can_decide(): void
    {
        $this->fakeOrder(status: 'FAILED');

        $order = Areeba::verify('1001', 'indicator-abc', 'indicator-abc', expectedAmount: '25.50', expectedCurrency: 'USD');

        $this->assertSame(OrderStatus::Failed, $order->status);
        $this->assertFalse($order->isPaid());
    }

    public function test_an_unknown_gateway_status_does_not_break_parsing(): void
    {
        $this->fakeOrder(status: 'SOMETHING_NEW');

        $order = Areeba::retrieveOrder('1001');

        $this->assertNull($order->status);
        $this->assertFalse($order->isPaid());
    }

    public function test_the_order_id_is_url_encoded_in_the_request_path(): void
    {
        Http::fake(['*' => Http::response(['result' => 'SUCCESS', 'id' => 'INV/2026 #1', 'status' => 'CAPTURED', 'amount' => 5, 'currency' => 'USD'])]);

        Areeba::retrieveOrder('INV/2026 #1');

        Http::assertSent(fn ($request) => $request->url() === self::API.'/order/INV%2F2026%20%231');
    }

    public function test_reads_are_retried_after_a_server_error(): void
    {
        Sleep::fake();
        Http::fake([
            self::API.'/order/1001' => Http::sequence()
                ->push(['result' => 'ERROR'], 503)
                ->push(['result' => 'SUCCESS', 'id' => '1001', 'status' => 'CAPTURED', 'amount' => 5, 'currency' => 'USD']),
        ]);

        $order = Areeba::retrieveOrder('1001');

        $this->assertTrue($order->isPaid());
        Http::assertSentCount(2);
    }
}
