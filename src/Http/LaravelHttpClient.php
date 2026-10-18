<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends the client's requests through Laravel's HTTP client, so Http::fake(),
 * Http::preventStrayRequests() and Telescope see them.
 */
final class LaravelHttpClient implements ClientInterface
{
    public function __construct(
        private readonly Factory $http,
        /** Seconds one request may take (an upload of a large file included). */
        private readonly float $timeout = 900,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $pending = $this->http->withHeaders($headers)
            ->connectTimeout(10)
            ->timeout((int) ceil($this->timeout));

        $body = $request->getBody();
        if ($body->getSize() !== 0) {
            $pending->withBody($body, $request->getHeaderLine('Content-Type'));
        }

        try {
            return $pending->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
