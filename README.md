# deploymate-laravel

> [!WARNING]
> Not ready for public use. Development/testing only. Expect breaking changes.

`deploymate-laravel` is a Laravel 12 dev-only package that helps you keep a **minimal** `deploy.yaml` manifest in each Laravel app repo (used by the Deploymate server CLI).

## Table of contents

- [What it does](#what-it-does)
- [Install](#install)
- [Quickstart](#quickstart)
- [Commands](#commands)
- [Manifest: deploy.yaml (v1)](#manifest-deployyaml-v1)
- [Modules: defaults.enabled](#modules-defaultsenabled)
- [Development](#development)
- [License](#license)

## What it does

- Creates `deploy.yaml` with an interactive wizard (Laravel Prompts)
- Validates `deploy.yaml` strictly (CI gate)
- Normalizes/fixes common mistakes (`deploymate:fix`)

Running any `deploymate:*` command prints an “under development” warning.

## Install

```bash
composer require --dev midhunmonachan/deploymate-laravel
```

## Quickstart

```bash
php artisan deploymate:init
php artisan deploymate:check
```

## Commands

- `php artisan deploymate:init` — interactive wizard to create `deploy.yaml`
- `php artisan deploymate:check` — strict validator (exits non-zero on failure)
- `php artisan deploymate:fix` — normalizes/fixes common issues and rewrites the file in a stable minimal format

All commands support `--path=...` (relative to app root unless absolute):

```bash
php artisan deploymate:check --path=config/deploy.yaml
```

## Manifest: `deploy.yaml` (v1)

Location: repo root of the Laravel app (`deploy.yaml`).

Top-level keys must be exactly:

- `version: 1`
- `instances:` non-empty list of objects with exactly `{ domain, env }`
- `defaults:` object with exactly `{ enabled: [...] }`

Example:

```yaml
version: 1
instances:
  - domain: "example.com"
    env: production
defaults:
  enabled: [https, postgresql, redis]
```

## Modules: `defaults.enabled`

Allowed values:

- `https` — HTTPS automation (certs/TLS) on the server
- `postgresql` — internal Postgres per instance
- `mysql` — internal MySQL per instance
- `redis` — internal Redis per instance
- `octane` — run the app with Octane
- `queue` — run a queue worker process
- `scheduler` — run a scheduler process

## Development

```bash
composer test
```

## License

MIT
