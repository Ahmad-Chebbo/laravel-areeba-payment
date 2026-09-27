<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Enums\GatewayResult;
use AhmadChebbo\AreebaPayment\Exceptions\GatewayException;
use AhmadChebbo\AreebaPayment\Facades\Areeba;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class TransactionTest extends TestCase
{
    private function transactionResponse(string $type, string $result = 'SUCCESS'): array
    {
        return [
            'result' => $result,
            'order' => ['id' => '1001', 'status' => 'PARTIALLY_REFUNDED'],
            'response' => ['gatewayCode' => $result === 'SUCCESS' ? 'APPROVED' : 'DECLINED'],
            'transaction' => ['id' => 'refund-1', 'type' => $type, 'amount' => 5, 'currency' => 'USD'],
        ];
    }

    public function test_refund_puts_a_refund_transaction_on_the_merchant_order(): void
    {
        Http::fake([self::API.'/order/1001/transaction/refund-1' => Http::response($this->transactionResponse('REFUND'))]);

        $result = Areeba::refund('1001', '5.00', transactionId: 'refund-1');

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request['apiOperation'] === 'REFUND'
            && $request['transaction'] === ['amount' => '5.00', 'currency' => 'USD']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(GatewayResult::Success, $result->result);
        $this->assertSame('refund-1', $result->transactionId);
        $this->assertSame('APPROVED', $result->gatewayCode);
    }

    public function test_refund_generates_a_transaction_id_when_none_is_given(): void
    {
        Http::fake([self::API.'/order/1001/transaction/*' => Http::response($this->transactionResponse('REFUND'))]);

        Areeba::refund('1001', '5.00');

        Http::assertSent(fn (Request $request) => preg_match('#/order/1001/transaction/[0-9a-f-]{36}$#', $request->url()) === 1);
    }

    public function test_order_and_transaction_ids_are_url_encoded_in_the_request_path(): void
    {
        Http::fake(['*' => Http::response($this->transactionResponse('REFUND'))]);

        Areeba::refund('INV/2026 #1', '5.00', transactionId: 'refund/1');

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/order/INV%2F2026%20%231/transaction/refund%2F1');
    }

    public function test_a_declined_transaction_is_returned_not_thrown(): void
    {
        Http::fake([self::API.'/order/1001/transaction/refund-1' => Http::response($this->transactionResponse('REFUND', 'FAILURE'))]);

        $result = Areeba::refund('1001', '5.00', transactionId: 'refund-1');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('DECLINED', $result->gatewayCode);
    }

    public function test_writes_are_not_retried_because_they_could_move_money_twice(): void
    {
        Http::fake([self::API.'/order/1001/transaction/refund-1' => Http::response(['result' => 'ERROR'], 503)]);

        try {
            Areeba::refund('1001', '5.00', transactionId: 'refund-1');
            $this->fail('Expected a GatewayException.');
        } catch (GatewayException) {
            Http::assertSentCount(1);
        }
    }

    public function test_capture_puts_a_capture_transaction(): void
    {
        Http::fake([self::API.'/order/1001/transaction/cap-1' => Http::response($this->transactionResponse('CAPTURE'))]);

        Areeba::capture('1001', '25.50', transactionId: 'cap-1');

        Http::assertSent(fn (Request $request) => $request['apiOperation'] === 'CAPTURE'
            && $request['transaction'] === ['amount' => '25.50', 'currency' => 'USD']);
    }

    public function test_pay_with_token_charges_a_stored_card(): void
    {
        Http::fake([self::API.'/order/2002/transaction/pay-1' => Http::response($this->transactionResponse('PAYMENT'))]);

        Areeba::payWithToken('2002', '9.99', '9876543210123456', transactionId: 'pay-1', extra: [
            'agreement' => ['id' => 'plan-7', 'type' => 'RECURRING'],
        ]);

        Http::assertSent(fn (Request $request) => $request['apiOperation'] === 'PAY'
            && $request['order'] === ['amount' => '9.99', 'currency' => 'USD']
            && $request['sourceOfFunds'] === ['type' => 'CARD', 'token' => '9876543210123456']
            && $request['agreement'] === ['id' => 'plan-7', 'type' => 'RECURRING']);
    }
}
