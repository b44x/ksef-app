# Architecture

## Decisions

**Laravel + Inertia + React, with the SDK used as it is.** The hard part of working with KSeF (encryption, XAdES, XSD,
corrections, offline, recovery after lost responses) lives in `b44x/ksef-php`. The app reuses it instead of porting it:
a port to another language would be weeks of work and a second set of bugs. Laravel brings what an application like this
needs without extra parts: users and sessions, queues, a scheduler, encryption of secrets, migrations, testing.
The UI is React (TypeScript, Tailwind) served by Inertia: pages are React components, but routing, validation, sessions
and data stay in Laravel, so there is no separate API to build and version, and still one application to deploy. React was
chosen for the invoice form (dynamic lines, autocomplete, live totals), which is where a server-rendered UI gets awkward.

**Cross-platform by packaging, not by rewriting.** The same application runs in a browser (Docker, a server, a NAS) and,
later, as a desktop app for Windows and macOS with the PHP runtime bundled (NativePHP). Desktop is a packaging concern
and comes after the core works; it starts with a small "hello world" spike to find the problems (signing, notarisation,
size) early.

**The KSeF layer is behind a port.** The rest of the app talks to `App\Ksef\KsefGateway`, never to the SDK directly.
The SDK is one adapter. If KSeF has to be driven differently one day (another SDK, another language, a service), only the
adapter changes.

**One company per installation, many in the model.** Every business record (invoices, contractors, series, credentials)
belongs to a `Company`. The first version shows one; multi-company is then a matter of the UI and permissions, not of
rewriting the data model.

## Layers

```
React pages (UI)                  resources/js/Pages
Controllers, form requests        app/Http
Application services              app/Services      issuing, receiving, synchronising
Ports and adapters                app/Ksef          KsefGateway, SdkKsefGateway, token store
Domain / persistence              app/Models        Company, Invoice, Contractor, ...
Queue and scheduler               app/Jobs          send, poll status, synchronise
```

## Where things live

| Concern | Code | Notes |
| --- | --- | --- |
| Issuing | `app/Invoicing` (`IssueInvoice`, `SdkInvoiceFactory`, `Totals`) | The only place that knows both the app's invoice and the SDK's. Money is never computed in PHP or JS: the SDK does it (`Totals`), and the sums are frozen on the invoice at issue (`net_total`, `vat_total`, `gross_total`, `vat_summary`). |
| Kinds of document | `Invoice::$kind`, `corrects_invoice_id`, `converted_from_id` | VAT invoice, proforma (never built as FA(3)), correction (KOR: "before" lines from `stateBefore()`, totals are the difference). |
| Printing | `app/Pdf` (`InvoiceView`, `InvoicePdf`, `TemplateSettings`), `resources/views/pdf` | One Blade view, three layouts, settings cleaned by `TemplateSettings::clean()`. The editor's live preview is the same PDF code. |
| Money owed | `Payment`, `Invoice::outstanding()`, `app/Reports/SalesReport` | Rejected invoices and proformas are not sales. |
| Repeating | `RecurringInvoice`, `GenerateRecurring`, `invoices:generate-recurring` | Daily at 05:00; missed runs are not made up. |
| Outside data | `app/Lookup` (`WhiteList`, `NbpRates`) | Public APIs, cached, failures are not cached. |
| Mail | `app/Mail`, `app/Notifications/BuildDigest` | `MAIL_*` in the environment. |

Foreign-currency invoices carry `exchange_rate` (NBP, last business day before the sale); the SDK converts the VAT.

## Languages

The interface is multilingual (English and Polish now). Texts are keyed by their English wording: PHP uses `__('...')`,
React uses `t('...')` (`resources/js/i18n.ts`), and both read `lang/<code>.json`. The language is the one the user picked
(kept in the session), else the browser's, else `APP_LOCALE`. A test fails when a text used in the frontend has no Polish
translation. To add a language: add it to `config/locales.php`, add `lang/<code>.json` and `lang/<code>/validation.php`.
Texts that come from the SDK's connection check (the messages, not the names) are in English for now.

## Secrets

KSeF tokens, certificates and private keys are stored encrypted (`Crypt`, `APP_KEY`), are never logged or shown again, and
the SDK's access tokens are kept in an encrypted token store, so a PHP request does not log in again.

## Background work

Sending, status polling and synchronising purchase invoices run as queued jobs. A desktop install runs them while the
app is open; a server install runs them continuously.
