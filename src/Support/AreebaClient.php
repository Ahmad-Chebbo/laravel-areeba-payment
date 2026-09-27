<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Support;

use AhmadChebbo\AreebaPayment\Enums\GatewayResult;
use AhmadChebbo\AreebaPayment\Exceptions\AreebaException;
use AhmadChebbo\AreebaPayment\Exceptions\GatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that talks HTTP to the gateway: auth, base URL, timeouts, retries and errors.
 */
final class AreebaClient
{
    public function __construct(
        private readonly string $gatewayUrl,
        private readonly string $apiVersion,
        private readonly ?string $merchantId,
        private readonly ?string $apiPassword,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            gatewayUrl: rtrim((string) $config['gateway_url'], '/'),
            apiVersion: (string) $config['api_version'],
            merchantId: $config['merchant_id'] ?: null,
            apiPassword: $config['api_password'] ?: null,
            timeout: (int) $config['timeout'],
            connectTimeout: (int) $config['connect_timeout'],
        );
    }

    public function gatewayUrl(): string
    {
        return $this->gatewayUrl;
    }

    public function get(string $path): array
    {
        return $this->send('GET', $path);
    }

    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, $body);
    }

    public function put(string $path, array $body): array
    {
        return $this->send('PUT', $path, $body);
    }

    public function delete(string $path): array
    {
        return $this->send('DELETE', $path);
    }

    /**
     * @throws AreebaException when credentials are missing
     * @throws GatewayException when the gateway rejects the request or cannot be reached
     */
    private function send(string $method, string $path, array $body = []): array
    {
        $this->assertConfigured();

        try {
            $response = $this->request()
                // Only reads are retried: a retried PAY or REFUND could move money twice.
                ->when($method === 'GET', fn (PendingRequest $http) => $http->retry(
                    [200, 1000],
                    when: fn (Throwable $e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                ))
                ->send($method, $path, $body === [] ? [] : ['json' => $body]);
        } catch (ConnectionException $e) {
            throw GatewayException::unreachable($e);
        }

        if ($response->failed() || $response->json('result') === GatewayResult::Error->value) {
            throw GatewayException::fromResponse($response);
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl("{$this->gatewayUrl}/api/rest/version/{$this->apiVersion}/merchant/{$this->merchantId}")
            ->withBasicAuth("merchant.{$this->merchantId}", (string) $this->apiPassword)
            ->acceptJson()
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);
    }

    private function assertConfigured(): void
    {
        $missing = array_keys(array_filter([
            'AREEBA_MERCHANT_ID' => ! $this->merchantId,
            'AREEBA_API_PASSWORD' => ! $this->apiPassword,
        ]));

        if ($missing !== []) {
            throw new AreebaException('Areeba credentials are missing. Set: '.implode(', ', $missing).'.');
        }
    }
}
