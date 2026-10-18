<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Console;

use Illuminate\Console\Command;
use Xmlvalid\Client;
use Xmlvalid\Document;
use Xmlvalid\Model\Validation;

/** Validates files or URLs from the command line (deploys, CI, a scheduled check). */
final class ValidateCommand extends Command
{
    protected $signature = 'xmlvalid:validate
        {documents* : XML files, PDFs, archives or http(s) URLs}
        {--schema= : A catalog slug (peppol-bis-3-invoice), a saved schema (my-12) or "auto"}
        {--schema-file= : Your own XSD, RelaxNG or DTD file}
        {--rules= : Your own Schematron (.sch) file, run after the schema check}
        {--doctype : Validate against the DTD the DOCTYPE names}
        {--warnings : List the warnings too}';

    protected $description = 'Validate XML documents with xmlvalid';

    public function handle(Client $client): int
    {
        $failed = 0;
        foreach ($this->documents() as $path) {
            $validation = $client->validate(
                preg_match('#^https?://#i', $path) === 1 ? Document::url($path) : Document::file($path),
                schema: $this->stringOption('schema'),
                schemaFile: $this->stringOption('schema-file'),
                doctype: (bool) $this->option('doctype'),
                rules: $this->stringOption('rules'),
            );

            if (! $validation->isValid()) {
                $failed++;
            }
            $this->report($path, $validation);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function report(string $path, Validation $validation): void
    {
        $result = $validation->result;
        $outcome = $result->outcome ?? $validation->status;
        $count = $result === null ? '' : match (true) {
            $result->errorsTotal > 0 => " ({$result->errorsTotal} " . ($result->errorsTotal === 1 ? 'error' : 'errors') . ')',
            $result->warningsTotal > 0 => " ({$result->warningsTotal} " . ($result->warningsTotal === 1 ? 'warning' : 'warnings') . ')',
            default => '',
        };

        $this->line(sprintf('<fg=%s>%s</> %s <fg=gray>%s%s</>', $validation->isValid() ? 'green' : 'red', $validation->isValid() ? '✓' : '✗', $path, $outcome, $count));

        foreach ($validation->errors() as $error) {
            $this->line('  ' . $error);
        }
        if ($result !== null && $result->errorsTruncated) {
            $this->line('  <fg=gray>… only the first ' . count($result->errors) . ' are listed</>');
        }
        if ($validation->error !== null) {
            $this->line('  ' . $validation->error);
        }
        if ($this->option('warnings')) {
            foreach ($validation->warnings() as $warning) {
                $this->line('  <fg=yellow>' . $warning . '</>');
            }
        }
    }

    /** @return list<string> */
    private function documents(): array
    {
        $documents = $this->argument('documents');

        return is_array($documents) ? array_values(array_filter($documents, is_string(...))) : [];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
