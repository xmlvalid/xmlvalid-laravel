<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

/** xmlvalid could not be reached (the client turns it into a TransportException). */
final class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
