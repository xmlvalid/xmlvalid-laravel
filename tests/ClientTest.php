<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Xmlvalid\Client;
use Xmlvalid\Document;
use Xmlvalid\Exception\TransportException;
use Xmlvalid\Laravel\Facades\Xmlvalid;
use Xmlvalid\Laravel\Testing\XmlvalidFake;

final class ClientTest extends TestCase
{
    public function test_the_client_is_configured_and_goes_through_laravel_http(): void
    {
        Http::fake(['xmlvalid.com/api/v1/usage' => Http::response(['plan' => 'pro', 'period' => ['start' => '2026-10-01T00:00:00+00:00', 'end' => '2026-11-01T00:00:00+00:00'], 'validations' => ['used' => 3, 'limit' => 10000]])]);

        self::assertSame(app(Client::class), app('xmlvalid'));
        self::assertSame(9997, Xmlvalid::usage()->remaining());

        Http::assertSent(fn(Request $request): bool => $request->url() === 'https://xmlvalid.com/api/v1/usage'
            && $request->method() === 'GET'
            && $request->header('Authorization') === ['Bearer xv_test']
            && str_starts_with($request->toPsrRequest()->getHeaderLine('User-Agent'), 'xmlvalid-php/'));
    }

    public function test_a_file_is_sent_as_the_body(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        $validation = Xmlvalid::validate(Document::file($this->fixture('invoice.xml')), schema: 'peppol-bis-3-invoice');

        self::assertTrue($validation->isValid());
        Http::assertSent(fn(Request $request): bool => $request->url() === 'https://xmlvalid.com/api/v1/validations?schema=peppol-bis-3-invoice&wait=10&name=invoice.xml'
            && $request->header('Content-Type') === ['application/xml']
            && $request->body() === file_get_contents($this->fixture('invoice.xml')));
    }

    public function test_the_fake_answers_in_the_api_shape(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(['line' => 139, 'rule' => 'BR-CO-16', 'path' => '/Invoice/cac:LegalMonetaryTotal', 'message' => 'Amount due'], 'Second')]);

        $validation = Xmlvalid::validate(Document::string('<Invoice/>'));

        self::assertSame(['/Invoice/cac:LegalMonetaryTotal', null], array_map(fn($error) => $error->path, $validation->errors()));
        self::assertNull($validation->result?->sha256);
        self::assertNull($validation->result?->engine);
    }

    public function test_multipart_keeps_its_boundary(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        Xmlvalid::validate(Document::string('<a/>'), schemaFile: $this->fixture('invoice.xml'));

        Http::assertSent(function (Request $request): bool {
            $type = $request->toPsrRequest()->getHeaderLine('Content-Type');

            return preg_match('/^multipart\/form-data; boundary=(\S+)$/', $type, $match) === 1
                && str_contains($request->body(), "--{$match[1]}\r\nContent-Disposition: form-data; name=\"schema_file\"; filename=\"invoice.xml\"");
        });
    }

    public function test_a_connection_failure_is_a_transport_exception(): void
    {
        Http::fake(fn() => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not resolve host');

        Xmlvalid::usage();
    }

    public function test_api_errors_are_thrown(): void
    {
        Http::fake(['xmlvalid.com/*' => Http::response(['error' => ['code' => 'unauthenticated', 'message' => 'The API key is not valid.']], 401)]);

        $this->expectException(\Xmlvalid\Exception\AuthenticationException::class);

        Xmlvalid::usage();
    }

    public function test_a_missing_key_is_explained(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('XMLVALID_API_KEY');

        config(['xmlvalid.key' => null]);
        app(Client::class);
    }

    public function test_the_fake_answers_like_the_api(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(['line' => 12, 'rule' => 'BR-CO-15', 'message' => 'Invoice total with VAT = total without VAT + VAT'], 'Second')]);

        $validation = Xmlvalid::validate(Document::string('<a/>'));

        self::assertFalse($validation->isValid());
        self::assertSame(['Line 12: [BR-CO-15] Invoice total with VAT = total without VAT + VAT', 'Second'], array_map(strval(...), $validation->errors()));
        self::assertSame('schematron', $validation->errors()[0]->type);
    }
}
