<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Console;

use Illuminate\Console\Command;
use Xmlvalid\Client;
use Xmlvalid\Model\Schema;

/** Lists the schemas to pass as --schema / ValidXml::schema(). */
final class SchemasCommand extends Command
{
    protected $signature = 'xmlvalid:schemas {search? : Part of a name or slug (peppol, xrechnung, ubl)}';

    protected $description = 'List your saved schemas and the xmlvalid catalog';

    public function handle(Client $client): int
    {
        $search = $this->argument('search');
        $search = is_string($search) ? mb_strtolower($search) : '';

        $schemas = array_filter(
            $client->schemas(),
            fn(Schema $schema): bool => $search === '' || str_contains(mb_strtolower("{$schema->schema} {$schema->name}"), $search),
        );

        $this->table(['Schema', 'Name', 'Kind', 'Rules'], array_map(fn(Schema $schema): array => [
            $schema->schema,
            $schema->name . ($schema->version !== null ? " {$schema->version}" : ''),
            $schema->kind,
            $schema->rules === [] ? '' : 'Schematron',
        ], $schemas));

        return self::SUCCESS;
    }
}
