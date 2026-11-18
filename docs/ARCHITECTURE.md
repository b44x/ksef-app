# Architecture

## Decisions

**Laravel + Livewire, with the SDK used as it is.** The hard part of working with KSeF (encryption, XAdES, XSD,
corrections, offline, recovery after lost responses) lives in `b44x/ksef-php`. The app reuses it instead of porting it:
a port to another language would be weeks of work and a second set of bugs. Laravel brings what an application like this
needs without extra parts: users and sessions, queues, a scheduler, encryption of secrets, migrations, testing.
The UI is server-rendered (Livewire), so there is one application to build, test and deploy.

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
Livewire components (UI)          app/Livewire
Application services              app/Services      issuing, receiving, synchronising
Ports and adapters                app/Ksef          KsefGateway, SdkKsefGateway, token store
Domain / persistence              app/Models        Company, Invoice, Contractor, ...
Queue and scheduler               app/Jobs          send, poll status, synchronise
```

## Secrets

KSeF tokens, certificates and private keys are stored encrypted (`Crypt`, `APP_KEY`), are never logged or shown again, and
the SDK's access tokens are kept in an encrypted token store, so a PHP request does not log in again.

## Background work

Sending, status polling and synchronising purchase invoices run as queued jobs. A desktop install runs them while the
app is open; a server install runs them continuously.
