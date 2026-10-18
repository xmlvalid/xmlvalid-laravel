<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Xmlvalid\Laravel\Testing\XmlvalidFake;

final class CommandsTest extends TestCase
{
    /** @param  array<string, mixed>  $parameters */
    private function command(string $command, array $parameters): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        self::assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    public function test_a_valid_document(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        $this->command('xmlvalid:validate', ['documents' => [$this->fixture('invoice.xml')], '--schema' => 'peppol-bis-3-invoice'])
            ->expectsOutputToContain('invoice.xml valid')
            ->assertSuccessful();
    }

    public function test_an_invalid_document_lists_its_errors_and_fails(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(['line' => 12, 'rule' => 'BR-CO-15', 'message' => 'Totals'])]);

        $this->command('xmlvalid:validate', ['documents' => ['https://example.com/feed.xml'], '--schema' => 'auto'])
            ->expectsOutputToContain('feed.xml invalid (1 error)')
            ->expectsOutputToContain('Line 12: [BR-CO-15] Totals')
            ->assertFailed();

        Http::assertSent(fn(Request $request): bool => $request['url'] === 'https://example.com/feed.xml' && $request['schema'] === 'auto');
    }

    public function test_schemas_are_listed_and_searched(): void
    {
        Http::fake(['xmlvalid.com/api/v1/schemas' => Http::response([
            'saved' => [['schema' => 'my-12', 'name' => 'order.xsd', 'kind' => 'xsd', 'namespace' => null]],
            'catalog' => [
                ['schema' => 'peppol-bis-3-invoice', 'name' => 'Peppol BIS Billing 3.0 Invoice', 'kind' => 'xsd', 'namespace' => null, 'version' => '3.0.20', 'rules' => ['EN 16931']],
                ['schema' => 'ubl-2.1-invoice', 'name' => 'UBL 2.1 Invoice', 'kind' => 'xsd', 'namespace' => null],
            ],
        ])]);

        $this->command('xmlvalid:schemas', ['search' => 'Peppol'])
            ->expectsTable(['Schema', 'Name', 'Kind', 'Rules'], [['peppol-bis-3-invoice', 'Peppol BIS Billing 3.0 Invoice 3.0.20', 'xsd', 'Schematron']])
            ->assertSuccessful();
    }
}
