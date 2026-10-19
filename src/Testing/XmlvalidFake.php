<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;

/**
 * API answers for your tests, through Laravel's Http::fake():
 *
 *     Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);
 *     Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(['line' => 12, 'rule' => 'BR-CO-15', 'message' => 'Invoice total …'])]);
 */
final class XmlvalidFake
{
    /** A finished validation that passed. */
    public static function valid(): PromiseInterface
    {
        return Factory::response(self::validation(true, []));
    }

    /**
     * A finished validation that found these errors.
     *
     * @param  string|array<string, mixed>  ...$errors  A message, or diagnostic fields (line, column, rule, message, …)
     */
    public static function invalid(string|array ...$errors): PromiseInterface
    {
        $errors = array_map(fn(string|array $error): array => [
            'line' => 0,
            'column' => 0,
            'level' => 'error',
            'type' => isset($error['rule']) ? 'schematron' : 'xsd',
            'rule' => null,
            'path' => null,
            'message' => '',
            'message_en' => null,
            'help_url' => null,
            'rule_url' => null,
            ...(is_string($error) ? ['message' => $error] : $error),
        ], $errors === [] ? ['The document is not valid.'] : array_values($errors));

        return Factory::response(self::validation(false, $errors));
    }

    /**
     * A validation as the API returns it, for answers of your own.
     *
     * @param  list<array<string, mixed>>  $errors
     * @return array<string, mixed>
     */
    public static function validation(bool $valid, array $errors): array
    {
        $now = gmdate('Y-m-d\TH:i:s\+00:00');

        return [
            'id' => 'val_fake',
            'status' => 'done',
            'progress' => 100,
            'created_at' => $now,
            'finished_at' => $now,
            'document' => ['source' => 'upload', 'name' => 'document.xml', 'size' => 0],
            'schema' => null,
            'rules' => null,
            'rule_sets' => [],
            'result' => [
                'valid' => $valid,
                'outcome' => $valid ? 'valid' : 'invalid',
                'errors_total' => count($errors),
                'warnings_total' => 0,
                'errors_truncated' => false,
                'errors' => $errors,
                'warnings' => [],
                'bytes' => 0,
                'duration_ms' => 1,
                'sha256' => null,
                'engine' => null,
            ],
            'error' => null,
            'links' => [],
        ];
    }
}
