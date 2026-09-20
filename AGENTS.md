# AGENTS.md

## What this is

A Laravel package (`masgeek/reliese-laravel-model-gen`) that reverse-engineers Eloquent models from a database schema. It is a fork of `reliese/laravel` (remote `upstream`). This is a Composer **library**, not a Laravel app: there is no `artisan` binary here, and the `code:models` command can only be exercised through tests. PSR-4 `Reliese\` -> `src/`.

## Commands

- `composer install` — install dependencies (vendor/ is gitignored).
- `vendor/bin/phpunit --no-coverage` — run the full suite. Always pass `--no-coverage`; coverage needs xdebug/pcov, which is not set up.
- No linter/static-analysis/formatter is configured. Don't invent one (no phpstan, pint, php-cs-fixer, etc.).

Tests are pure unit tests using Mockery — no live DB, no services. 107 tests, run in <1s.

## Architecture

- `src/Meta/` — DB introspection. `SchemaManager` maps an Illuminate connection class to a mapper implementing the `Schema` interface. Built-ins: `MySql\Schema`, `Postgres\Schema`, `Sqlite\Schema`. Lookup falls back over `instanceof` (base-class connections like PgBouncer wrappers resolve automatically) and honors a `custom_mappers` key in `config/models.php`.
- `src/Coders/` — generation. `CodersServiceProvider` (deferred) registers the `code:models` Artisan command and binds `ModelFactory` as a singleton from `config/models.php`. `src/Coders/Model/` is the generator (Model, Factory, Config, `Relations/`); `Templates/` holds the stubs.
- `src/Database/Eloquent/` — optional base-model traits (BitBooleans, BlamableBehavior, WhoDidIt) generated models can inherit via the `parent` config key.
- `src/Support/` — `Classify` (the plural/studly naming helpers used throughout), `Dumper`.

## Conventions & gotchas

- `tests/TestCase.php` is a **non-namespaced** `TestCase` (extends `PHPUnit\Framework\TestCase`), loaded via the `autoload-dev` classmap. Test files use that global class directly. New tests must end in `Test.php` under `tests/` for phpunit.xml (`suffix="Test.php"`) to discover them.
- `composer.lock` is gitignored (present locally for reproducibility, but never committed).
- The `code:models` command supports `--schema`, `--connection`, `--table`, `--view`, `--pg-schema`, and `--dry-run`. `--pg-schema` resolution order: option -> `DB_SCHEMA` env -> connection config -> `public`.
- `config/models.php` is the single source of generation behavior; `docs/improvements.md` and `ENHANCEMENTS.md` track implemented/planned improvements — check them before adding a feature that may already exist.
- Default integration branch is `develop`. CI (`UnitTests` workflow) runs `vendor/bin/phpunit --no-coverage` on PHP 8.3/8.4/8.5 (`prefer-stable`). After a green run, `pr-automation` auto-approves the PR using a GitHub App token (`vars.CLIENT_ID` + `secrets.APP_PRIVATE_KEY`). Pushing to `develop` auto-opens a "Next release" PR to `main`; pushing to `main` bumps the `v*` tag and drafts a GitHub release (`bump-and-tag` workflow).