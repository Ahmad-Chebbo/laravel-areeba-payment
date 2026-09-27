<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Exceptions;

use Exception;
use Throwable;

class AreebaException extends Exception
{
    public function __construct(string $message = '', protected array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Laravel adds this to the log entry when the exception is reported. */
    public function context(): array
    {
        return $this->context;
    }
}
