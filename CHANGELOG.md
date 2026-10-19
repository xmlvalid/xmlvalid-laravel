# Changelog

## 1.2.0

- `XmlvalidFake` answers as the API does now: `path` on each error (where a business rule fired),
  `sha256` and `engine` on the result. Needs `xmlvalid/xmlvalid-php` 1.2, whose models read them.

## 1.1.0

- `ValidXml::rulesVersion()` and `xmlvalid:validate --rules-version` pin business rule releases
  (`peppol-bis-3-ubl@3.0.21`); `xmlvalid:validate` names the releases used and `xmlvalid:schemas`
  lists them. Needs `xmlvalid/xmlvalid-php` 1.1.

## 1.0.0

- First release: the `ValidXml` rule, the `Xmlvalid` facade, webhooks as events
  (`ValidationCompleted`, `MonitorFailed`, `MonitorRecovered`, `WebhookReceived`),
  `xmlvalid:validate` and `xmlvalid:schemas`, `XmlvalidFake` for tests.
