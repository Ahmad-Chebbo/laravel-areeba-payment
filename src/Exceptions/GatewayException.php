<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * The gateway rejected a request (result ERROR or a non-2xx status) or could not be reached.
 * A declined payment is not an error: it comes back as a TransactionResult.
 */
class GatewayException extends AreebaException
{
    public static function fromResponse(Response $response): self
    {
        return new self(
            $response->json('error.explanation') ?? "Areeba request failed with HTTP {$response->status()}.",
            [
                'status' => $response->status(),
                'cause' => $response->json('error.cause'),
                'field' => $response->json('error.field'),
            ],
        );
    }

    public static function unreachable(ConnectionException $e): self
    {
        return new self('Could not reach the Areeba gateway: '.$e->getMessage(), [], $e);
    }
}
