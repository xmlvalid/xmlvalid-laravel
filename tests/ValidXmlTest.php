<?php

declare(strict_types=1);

namespace Xmlvalid\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Xmlvalid\Laravel\Rules\ValidXml;
use Xmlvalid\Laravel\Testing\XmlvalidFake;

final class ValidXmlTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function messages(array $data, ValidXml $rule): array
    {
        $validator = Validator::make($data, ['invoice' => ['required', $rule]]);

        return array_values(array_filter($validator->errors()->get('invoice'), is_string(...)));
    }

    public function test_a_valid_upload_passes_and_keeps_its_name(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);
        $rule = ValidXml::schema('peppol-bis-3-invoice');

        $upload = UploadedFile::fake()->createWithContent('INV-12.xml', (string) file_get_contents($this->fixture('invoice.xml')));
        self::assertSame([], $this->messages(['invoice' => $upload], $rule));

        self::assertSame('val_fake', $rule->validation()?->id);
        Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'schema=peppol-bis-3-invoice')
            && str_contains($request->url(), 'name=INV-12.xml'));
    }

    public function test_an_invalid_document_fails_with_its_first_error_and_a_count(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(['line' => 12, 'rule' => 'BR-CO-15', 'message' => 'Invoice total with VAT = total without VAT + VAT'], 'Second', 'Third')]);

        self::assertSame([
            'The invoice is not valid: Line 12: [BR-CO-15] Invoice total with VAT = total without VAT + VAT',
            'The invoice has 2 more errors.',
        ], $this->messages(['invoice' => '<Invoice/>'], new ValidXml()));
    }

    public function test_more_errors_can_be_listed(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid('First', 'Second', 'Third')]);

        self::assertSame([
            'The invoice is not valid: First',
            'The invoice is not valid: Second',
            'The invoice has one more error.',
        ], $this->messages(['invoice' => '<Invoice/>'], ValidXml::schema('auto')->errors(2)));
    }

    public function test_text_is_sent_as_a_document(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        self::assertSame([], $this->messages(['invoice' => '<Invoice/>'], new ValidXml()));
        Http::assertSent(fn(Request $request): bool => $request->body() === '<Invoice/>');
    }

    public function test_something_else_is_not_a_document(): void
    {
        self::assertSame(['The invoice must be an XML file or text.'], $this->messages(['invoice' => ['a']], new ValidXml()));
        Http::assertNothingSent();
    }

    public function test_a_validation_that_failed_on_xmlvalids_side_is_not_checked(): void
    {
        $failed = XmlvalidFake::validation(false, []);
        $failed['status'] = 'failed';
        $failed['result'] = null;
        $failed['error'] = 'The document could not be read from storage.';
        Http::fake(['xmlvalid.com/*' => Http::response($failed)]);

        self::assertSame(['The invoice could not be validated: The document could not be read from storage.'], $this->messages(['invoice' => '<a/>'], new ValidXml()));
    }

    public function test_own_schema_and_rules_go_as_multipart(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        $this->messages(['invoice' => '<a/>'], ValidXml::schemaFile($this->fixture('invoice.xml'))->withRules($this->fixture('invoice.xml'))->timeout(30));

        Http::assertSent(fn(Request $request): bool => str_contains($request->body(), 'name="schema_file"')
            && str_contains($request->body(), 'name="rules_file"'));
    }

    public function test_doctype(): void
    {
        Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

        $this->messages(['invoice' => '<a/>'], ValidXml::doctype());

        Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'doctype=1'));
    }
}
