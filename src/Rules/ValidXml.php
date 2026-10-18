<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\App;
use Illuminate\Translation\PotentiallyTranslatedString;
use SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xmlvalid\Client;
use Xmlvalid\Document;
use Xmlvalid\Model\Result;
use Xmlvalid\Model\Validation;

/**
 * Validates an uploaded file or XML text with xmlvalid.
 *
 *     $request->validate([
 *         'invoice' => ['required', 'file', ValidXml::schema('peppol-bis-3-invoice')],
 *     ]);
 *
 * Errors of the API itself (a wrong key, an unknown schema, the monthly quota)
 * are thrown, not reported as a failed field.
 */
final class ValidXml implements ValidationRule
{
    private ?string $schemaFile = null;

    /** @var string|array<string, string>|null */
    private string|array|null $rules = null;

    private bool $doctype = false;

    private ?float $timeout = null;

    private int $errors = 1;

    private ?Validation $validation = null;

    /** @param  string|null  $schema  A catalog slug (https://xmlvalid.com/schemas), a saved schema's id, or null to let the document say */
    public function __construct(private readonly ?string $schema = null) {}

    /** Checks against a catalog schema, a saved schema or "auto" (e-invoices: the profile they name). */
    public static function schema(string $schema): self
    {
        return new self($schema);
    }

    /** Checks against your own schema file (.xsd, .rng, .rnc or .dtd; a schema with includes is saved under Dashboard → Schemas and used by its id). */
    public static function schemaFile(string $path): self
    {
        $rule = new self();
        $rule->schemaFile = $path;

        return $rule;
    }

    /** Checks against the DTD the document's DOCTYPE names. */
    public static function doctype(): self
    {
        $rule = new self();
        $rule->doctype = true;

        return $rule;
    }

    /**
     * Also runs your own Schematron rules after the schema check.
     *
     * @param  string|array<string, string>  $rules  A .sch file, or [relative path => file] for a .sch with includes
     */
    public function withRules(string|array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /** Seconds to wait for the result (the config's xmlvalid.timeout by default). */
    public function timeout(float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /** How many of the errors become messages (1 by default, plus a count of the rest). */
    public function errors(int $count): self
    {
        $this->errors = max(1, $count);

        return $this;
    }

    /** The last validation run, to keep its id or link its report. */
    public function validation(): ?Validation
    {
        return $this->validation;
    }

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $document = $this->document($value);
        if ($document === null) {
            $fail('xmlvalid::validation.not_a_document')->translate();

            return;
        }

        $this->validation = App::make(Client::class)->validate(
            $document,
            schema: $this->schema,
            schemaFile: $this->schemaFile,
            doctype: $this->doctype,
            rules: $this->rules,
            timeout: $this->timeout,
        );

        $result = $this->validation->result;
        if ($result !== null && $result->valid) {
            return;
        }

        if ($result === null || ! in_array($result->outcome, ['invalid', 'input'], true)) {
            $error = $result?->firstError() ?? $this->validation->error ?? $this->validation->status;
            $fail('xmlvalid::validation.not_checked')->translate(['error' => (string) $error]);

            return;
        }

        $this->failWithErrors($result, $fail);
    }

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    private function failWithErrors(Result $result, Closure $fail): void
    {
        $shown = array_slice($result->errors, 0, $this->errors);
        foreach ($shown as $error) {
            $fail('xmlvalid::validation.invalid')->translate(['error' => (string) $error]);
        }

        $rest = max($result->errorsTotal, count($result->errors)) - count($shown);
        if ($rest > 0) {
            $fail('xmlvalid::validation.more')->translateChoice($rest, ['count' => (string) $rest]);
        }
    }

    private function document(mixed $value): ?Document
    {
        if ($value instanceof UploadedFile) {
            return $value->isValid() ? Document::file($value->getPathname(), $value->getClientOriginalName()) : null;
        }
        if ($value instanceof SplFileInfo) {
            return $value->isFile() ? Document::file($value->getPathname()) : null;
        }
        if (is_string($value) && trim($value) !== '') {
            return Document::string($value);
        }

        return null;
    }
}
