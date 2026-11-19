# Contributing

Thank you for helping. A few rules keep the code easy to work on for everyone.

## Setup

```
composer install && npm ci
cp .env.example .env && php artisan key:generate && php artisan migrate
composer run dev      # or: php artisan serve  +  npm run dev
```

## Before you open a pull request

```
composer check        # Pint (style), PHPStan/Larastan (static analysis), tests
npm run lint && npm run format:check && npm run typecheck
```

CI runs the same checks on PHP 8.3, 8.4 and 8.5.

## Principles

- **KISS, DRY, SOLID, but only where they pay for themselves.** Do not add a layer or an abstraction for a case that does
  not exist yet; do remove real duplication.
- **Controllers stay thin.** Validation lives in form requests, business logic in actions/services, KSeF access behind
  `App\Ksef\KsefGateway`. Nothing outside `app/Ksef` talks to the SDK.
- **Every business record belongs to a `Company`**, so that several companies can be supported later.
- **Secrets are never logged, shown again or sent to the browser.**
- **Tests describe behaviour.** A bug fix starts with a failing test.
- **Every user-visible text is translated** (`__()` in PHP, `t()` in React, then add it to `lang/pl.json`); a test enforces it.
- Keep dependencies current; Dependabot opens the updates.

## Commits and branches

Short imperative subject, a body that says why. Branch from `develop` (`feat/...`, `fix/...`, `docs/...`, `chore/...`);
`main` is for releases.
