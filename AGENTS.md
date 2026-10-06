# AGENTS.md

## What this is

A Laravel package (`masgeek/reliese-laravel-model-gen`) that reverse-engineers Eloquent models from a database schema. It is a fork of `reliese/laravel` (remote `upstream`). This is a Composer **library**, not a Laravel app: there is no `artisan` binary here, and the `code:models` command can only be exercised through tests. PSR-4 `Reliese\` -> `src/`.

## Commands

- `composer install` — install dependencies (vendor/ is gitignored).
- `vendor/bin/phpunit --no-coverage` — run the full suite. Always pass `--no-coverage`; coverage needs xdebug/pcov, which is not set up.
- Single test: `vendor/bin/phpunit --no-coverage --filter ClassifyTest` (or `path/to/SomeTest.php`).
- `vendor/bin/phpstan analyse` — config in `phpstan.neon` (level 1, `src/`).
- `vendor/bin/php-cs-fixer check --diff` / `fix` — config in `.php-cs-fixer.dist.php` (PSR-12 + import sorting). `line_ending` is disabled on purpose: the git index is LF while Windows checkouts are CRLF, so enforcing it locally rewrites files.
- `vendor/bin/pest` — same suite via Pest (what CI runs). `pest --no-configuration` is unsupported in Pest 3.
- No `artisan`; nothing to boot locally.

CI (`unit-test.yml`) runs `composer validate`, `php -l` over `src/`, then phpstan -> php-cs-fixer -> pest on PHP 8.3/8.4/8.5. Run all four locally before pushing.

Tests are pure unit tests using Mockery — no live DB, no services. 114 tests, run in <1s. `tests/bootstrap.php` only requires `vendor/autoload.php`; there is no Laravel app bootstrapping, so anything needing a container/DB must be mocked.

## Architecture

- `src/Meta/` — DB introspection. `SchemaManager` maps an Illuminate connection class to a mapper implementing the `Schema` interface. Built-ins: `MySql\Schema`, `Postgres\Schema`, `Sqlite\Schema`. Lookup falls back over `instanceof` (base-class connections like PgBouncer wrappers resolve automatically) and honors a `custom_mappers` key in `config/models.php`.
- `src/Coders/` — generation. `CodersServiceProvider` (deferred) registers the `code:models` Artisan command and binds `ModelFactory` as a singleton from `config/models.php`. `src/Coders/Model/` is the generator (Model, Factory, Config, `Relations/`); `Templates/` holds the stubs.
- `src/Database/Eloquent/` — optional base-model traits (BitBooleans, BlamableBehavior, WhoDidIt) generated models can inherit via the `parent` config key.
- `src/Support/` — `Classify` (the plural/studly naming helpers used throughout), `Dumper`.

## Conventions & gotchas

- `tests/TestCase.php` is a **non-namespaced** `TestCase` (extends `PHPUnit\Framework\TestCase`), loaded via the `autoload-dev` classmap. Test files use that global class directly. New tests must end in `Test.php` under `tests/` for phpunit.xml (`suffix="Test.php"`) to discover them.
- `composer.lock` is gitignored (present locally for reproducibility, but never committed).
- `phpunit.xml.bak` is a stale PHPUnit 9 config; the live config is `phpunit.xml` (PHPUnit 11). Don't edit the `.bak`.
- CI also runs `php -l` over `src/`; keep `src/` syntax clean for every PHP 8.2+ syntax level even though CI only tests 8.3+.
- The `code:models` command lives at `src/Coders/Console/CodeModelsCommand.php` and supports `--schema` (`-s`), `--connection` (`-c`), `--table` (`-t`), `--view`, `--pg-schema`, and `--dry-run`. `--pg-schema` resolution order: option -> `DB_SCHEMA` env -> connection config -> `public`.
- `config/models.php` is the single source of generation behavior; `docs/improvements.md` and `ENHANCEMENTS.md` track implemented/planned improvements — check them before adding a feature that may already exist.
- Default integration branch is `develop`. After a green run, `pr-automation` auto-approves the PR using a GitHub App token (`vars.CLIENT_ID` + `secrets.APP_PRIVATE_KEY`). Pushing to `develop` auto-opens a "Next release" PR to `main`; pushing to `main` bumps the `v*` tag and drafts a GitHub release (`bump-and-tag` workflow).