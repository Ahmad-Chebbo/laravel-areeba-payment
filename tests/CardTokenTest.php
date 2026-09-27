<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Facades\Areeba;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class CardTokenTest extends TestCase
{
    private function tokenResponse(): array
    {
        return [
            'result' => 'SUCCESS',
            'status' => 'VALID',
            'token' => '9876543210123456',
            'sourceOfFunds' => [
                'type' => 'CARD',
                'provided' => ['card' => [
                    'brand' => 'MASTERCARD',
                    'scheme' => 'MASTERCARD',
                    'number' => '512345xxxxxx0008',
                    'expiry' => '0539',
                    'nameOnCard' => 'Jane Doe',
                ]],
            ],
        ];
    }

    public function test_tokenize_stores_the_card_from_a_checkout_session(): void
    {
        Http::fake([self::API.'/token' => Http::response($this->tokenResponse(), 201)]);

        $card = Areeba::tokenize('SESSION0002');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['session'] === ['id' => 'SESSION0002']
            && $request['sourceOfFunds'] === ['type' => 'CARD']);

        $this->assertSame('9876543210123456', $card->token);
        $this->assertTrue($card->isValid());
        $this->assertSame('0008', $card->lastFour);
        $this->assertSame('05', $card->expiryMonth);
        $this->assertSame('39', $card->expiryYear);
        $this->assertSame('MASTERCARD', $card->scheme);
        $this->assertSame('Jane Doe', $card->nameOnCard);
    }

    public function test_retrieve_token_reads_the_stored_card(): void
    {
        Http::fake([self::API.'/token/9876543210123456' => Http::response($this->tokenResponse())]);

        $this->assertSame('0008', Areeba::retrieveToken('9876543210123456')->lastFour);
    }

    public function test_the_token_is_url_encoded_in_the_request_path(): void
    {
        Http::fake(['*' => Http::response($this->tokenResponse())]);

        Areeba::retrieveToken('tok/1');

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/token/tok%2F1');
    }

    public function test_delete_token_removes_the_card_from_the_gateway(): void
    {
        Http::fake([self::API.'/token/9876543210123456' => Http::response(['result' => 'SUCCESS'])]);

        Areeba::deleteToken('9876543210123456');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
    }
}
