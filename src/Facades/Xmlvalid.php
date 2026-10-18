<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Xmlvalid\Client;

/**
 * @method static \Xmlvalid\Model\Validation validate(\Xmlvalid\Document $document, ?string $schema = null, ?string $schemaFile = null, bool $doctype = false, string|array<string, string>|null $rules = null, ?float $timeout = null, string|list<string>|null $rulesVersion = null)
 * @method static \Xmlvalid\Model\Validation submit(\Xmlvalid\Document $document, ?string $schema = null, ?string $schemaFile = null, bool $doctype = false, string|array<string, string>|null $rules = null, int $wait = 0, string|list<string>|null $rulesVersion = null)
 * @method static \Xmlvalid\Model\Validation waitFor(\Xmlvalid\Model\Validation|string $validation, ?float $timeout = null)
 * @method static \Xmlvalid\Model\Validation validation(string $id)
 * @method static \Xmlvalid\Model\Validation cancel(string $id)
 * @method static string report(string $id)
 * @method static \Xmlvalid\Model\ReportLink share(list<string> $ids)
 * @method static \Xmlvalid\Model\Usage usage()
 * @method static list<\Xmlvalid\Model\Schema> schemas()
 *
 * @see Client
 */
final class Xmlvalid extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
