<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Enums;

/**
 * The top-level `result` of a gateway response.
 */
enum GatewayResult: string
{
    case Success = 'SUCCESS';
    case Pending = 'PENDING';
    case Failure = 'FAILURE';
    case Unknown = 'UNKNOWN';
    case Error = 'ERROR';
}
