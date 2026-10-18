# xmlvalid for Laravel

Laravel integration for the [xmlvalid API](https://xmlvalid.com/docs/api): validate uploaded XML
and e-invoices in your forms and jobs against XSD, DTD, RelaxNG and Schematron business rules.
Peppol BIS, XRechnung, Factur-X / ZUGFeRD, EN 16931, UBL, CII and national rules (France, Italy,
Croatia, Romania and more) are in the [schema catalog](https://xmlvalid.com/schemas); your own
schemas work too.

- A `ValidXml` validation rule, with the failing line and business rule in the message
- The `Xmlvalid` facade for everything else (built on [xmlvalid-php](https://github.com/xmlvalid/xmlvalid-php))
- Webhooks as Laravel events, with the signature checked
- `php artisan xmlvalid:validate` for deploys and CI
- Requests go through Laravel's HTTP client: `Http::fake()` works in your tests

Laravel 12 and 13, PHP 8.2+.

## Install

```bash
composer require xmlvalid/xmlvalid-laravel
```

Add an API key from [Dashboard → API keys](https://xmlvalid.com/dashboard/api) to `.env`:

```dotenv
XMLVALID_API_KEY=xv_…
```

To change the defaults: `php artisan vendor:publish --tag=xmlvalid-config`.

## Validate uploads

```php
use Xmlvalid\Laravel\Rules\ValidXml;

$request->validate([
    'invoice' => ['required', 'file', 'max:10240', ValidXml::schema('peppol-bis-3-invoice')],
]);
```

An invalid invoice fails with its first error, and a count of the rest:

```
The invoice is not valid: Line 139: [BR-CO-16] Amount due for payment (BT-115) = Invoice total amount with VAT (BT-112) - Paid amount (BT-113) + Rounding amount (BT-114).
The invoice has 3 more errors.
```

| Rule | Validates against |
|---|---|
| `ValidXml::schema('peppol-bis-3-invoice')` | A [catalog](https://xmlvalid.com/schemas) entry, with its business rules |
| `ValidXml::schema('auto')` | The catalog profile an e-invoice names (BT-24), for mixed invoices |
| `ValidXml::schema('my-12')` | One of your saved schemas |
| `ValidXml::schemaFile(resource_path('schemas/order.xsd'))` | An XSD, DTD or RelaxNG file of yours |
| `ValidXml::doctype()` | The DOCTYPE the document declares |
| `new ValidXml()` | Well-formedness only |

Options:

```php
ValidXml::schema('xrechnung-3-ubl')
    ->withRules(resource_path('rules/our-rules.sch'))   // your own Schematron, after the schema check
    ->errors(5)                                         // list up to 5 errors (1 by default)
    ->timeout(30);                                      // seconds to wait (config: xmlvalid.timeout)
```

The value can be an uploaded file, any file (`SplFileInfo`) or XML text. The validation itself
is kept, to store its id or link its report:

```php
$rule = ValidXml::schema('auto');
$request->validate(['invoice' => ['required', 'file', $rule]]);

$invoice->update(['validation_id' => $rule->validation()->id]);
```

Errors of the API itself (a revoked key, an unknown schema, the monthly quota used up) are
thrown as exceptions (see below), not reported as an invalid field.

Messages are in `lang/vendor/xmlvalid` after `php artisan vendor:publish --tag=xmlvalid-lang`.

## The facade

Everything the [PHP client](https://github.com/xmlvalid/xmlvalid-php) does:

```php
use Xmlvalid\Document;
use Xmlvalid\Laravel\Facades\Xmlvalid;

$validation = Xmlvalid::validate(
    Document::file(storage_path('app/invoices/INV-12.xml')),
    schema: 'peppol-bis-3-invoice',
);

$validation->isValid();
foreach ($validation->errors() as $error) {
    $error->line; $error->rule; $error->message; // (string) $error: "Line 139: [BR-CO-16] …"
}

Xmlvalid::submit(Document::url('https://example.com/feed.xml'), schema: 'auto'); // queue it, answer at once
Xmlvalid::waitFor($id);
Xmlvalid::report($id);     // the HTML report
Xmlvalid::share([$id]);    // a public link for 30 days (Pro)
Xmlvalid::usage();         // this month's validations
Xmlvalid::schemas();       // your saved schemas, then the catalog
```

Or inject `Xmlvalid\Client`. Large files are streamed, not loaded into memory. For a long
validation in a queued job, `submit()` and a [webhook](#webhooks) save keeping a worker busy.

## Webhooks

On Pro, xmlvalid can call your app when a validation finishes or a URL monitor changes state.
Add an endpoint under [Dashboard → Webhooks](https://xmlvalid.com/dashboard/webhooks) with the URL
`https://your-app.com/xmlvalid/webhook`, and its signing secret to `.env`:

```dotenv
XMLVALID_WEBHOOK_SECRET=whsec_…
```

The route is registered once the secret is set (outside the `web` group, so without CSRF). It
checks the signature and dispatches events:

| Event | When | Properties |
|---|---|---|
| `ValidationCompleted` | An API validation finished | `$validation`, `$event` |
| `MonitorFailed` | A monitored URL started failing | `$monitor`, `$check`, `$event` |
| `MonitorRecovered` | A monitored URL passes again | `$monitor`, `$check`, `$event` |
| `WebhookReceived` | Any of them, and test pings | `$event` |

```php
use Illuminate\Support\Facades\Event;
use Xmlvalid\Laravel\Events\ValidationCompleted;

Event::listen(function (ValidationCompleted $completed) {
    Invoice::where('validation_id', $completed->validation->id)
        ->update(['valid' => $completed->validation->isValid()]);
});
```

A retried delivery has the same `$event->id`. The path, the middleware and the accepted clock
skew are in the config.

## Artisan

```bash
php artisan xmlvalid:validate storage/app/export/*.xml --schema=peppol-bis-3-invoice
php artisan xmlvalid:validate https://example.com/feed.xml --schema-file=resources/feed.xsd --warnings
php artisan xmlvalid:schemas xrechnung
```

`xmlvalid:validate` lists the errors and exits with 1 when a document is not valid.

## Testing

Requests go through Laravel's HTTP client, so fake them. `XmlvalidFake` builds the answers:

```php
use Illuminate\Support\Facades\Http;
use Xmlvalid\Laravel\Testing\XmlvalidFake;

Http::fake(['xmlvalid.com/*' => XmlvalidFake::valid()]);

Http::fake(['xmlvalid.com/*' => XmlvalidFake::invalid(
    ['line' => 139, 'rule' => 'BR-CO-16', 'message' => 'Amount due for payment …'],
)]);
```

## Errors

Every exception extends `Xmlvalid\Exception\XmlvalidException`:

| Exception | When |
|---|---|
| `AuthenticationException` | 401: no key, a revoked key |
| `PlanLimitException` | 403: not in your plan |
| `NotFoundException` | 404: no such validation, or it expired (24 hours) |
| `DocumentTooLargeException` | 413: over your plan's size limit |
| `InvalidRequestException` | 422: unknown schema, a bad URL, … |
| `QuotaExceededException` | 429: this month's validations are used up |
| `RateLimitedException` | 429: still limited after the retries |
| `ServerException` | 5xx |
| `TransportException` | xmlvalid could not be reached |
| `TimeoutException` | Still running after the timeout; it carries the validation |

## Configuration

```dotenv
XMLVALID_API_KEY=xv_…
XMLVALID_TIMEOUT=900           # seconds to wait for a result in all
XMLVALID_WAIT=10               # seconds the API holds a new request (0-60)
XMLVALID_RETRIES=3             # retries of a 429 rate_limited
XMLVALID_WEBHOOK_SECRET=whsec_…
XMLVALID_WEBHOOK_PATH=xmlvalid/webhook
```

## Development

```bash
composer install
composer test        # PHPUnit with Orchestra Testbench
composer analyse     # PHPStan (Larastan), level max
composer lint        # Pint (PER)
```

## License

MIT
