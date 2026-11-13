# AGENTS.md

## What this is

A Laravel package (`masgeek/reliese-laravel-model-gen`) that reverse-engineers Eloquent models from a database schema. It is a fork of `reliese/laravel` (remote `upstream`). This is a Composer **library**, not a Laravel app: there is no `artisan` binary here, and the `code:models` command can only be exercised through tests. PSR-4 `Reliese\` -> `src/`. Requires PHP `^8.2` and `doctrine/dbal ^4`; Illuminate deps are `>=5.1`.

## Commands

Composer script aliases (defined in `composer.json`): `composer test` (`phpunit --no-coverage`), `composer phpstan`, `composer cs-check`, `composer cs-fix`. The same binaries work directly.

- `composer install` — install dependencies (`vendor/` is gitignored).
- `vendor/bin/phpunit --no-coverage` — full suite. Always pass `--no-coverage`; coverage needs xdebug/pcov, which is not set up.
- Single test: `vendor/bin/phpunit --no-coverage --filter ClassifyTest` (or pass a test file path).
- `vendor/bin/phpstan analyse` — config in `phpstan.neon` (level 1, `src/`).
- `vendor/bin/php-cs-fixer check --diff` / `fix` — config in `.php-cs-fixer.dist.php` (PSR-12 + alpha import sorting, short arrays, single quotes, no trailing commas). `line_ending` is disabled on purpose: the git index is LF while Windows checkouts are CRLF, so enforcing it locally rewrites files.
- `vendor/bin/pest` — same suite via Pest (what CI runs). There is no `pest.php`; Pest just executes the PHPUnit tests. `pest --no-configuration` is unsupported in Pest 3.
- No `artisan`; nothing to boot locally.

CI (`unit-test.yml`) runs `composer validate`, `php -l` over `src/`, then phpstan -> php-cs-fixer -> pest on PHP 8.3/8.4/8.5. Run all four locally before pushing.

Tests are pure unit tests using Mockery — no live DB, no services; the full suite runs in <1s. `tests/bootstrap.php` only requires `vendor/autoload.php`; there is no Laravel app bootstrapping, so anything needing a container/DB must be mocked. `orchestra/testbench` is installed but unused.

## Architecture

- `src/Meta/` — DB introspection. `SchemaManager` maps an Illuminate connection class to a mapper implementing the `Schema` interface. Built-ins: `MySql\Schema` (also MariaDb), `Postgres\Schema`, `Sqlite\Schema`. Lookup tries exact class then falls back over `instanceof`, preferring the most-derived registered class (base-class connections like PgBouncer wrappers resolve automatically). `custom_mappers` in `config/models.php` is applied by `CodersServiceProvider::registerCustomMappers()`, which calls `SchemaManager::register()` and validates the mapper exists and implements `Schema`.
- `src/Coders/` — generation. `CodersServiceProvider` (deferred) publishes `config/models.php` as tag `reliese-models`, registers the `code:models` command, and binds `ModelFactory` as a singleton. Flow: command -> `Model\Factory` -> `Model`, `ModelManager`, `Mutator`, `Relations/`; `Model\Config` wraps the `models` config. `src/Coders/Model/Templates/` holds two extensionless `{{placeholder}}` stubs (`model`, `user_model`); `model` is overridable per model via the `*.template.model` config key.
- `src/Database/Eloquent/` — optional base-model traits (`BitBooleans`, `BlamableBehavior`, `WhoDidIt`) generated models can inherit via the `parent` config key.
- `src/Support/` — `Classify` (plural/studly naming helpers used throughout), `Dumper`.

## Conventions & gotchas

- `tests/TestCase.php` is a **non-namespaced** `TestCase` (extends `PHPUnit\Framework\TestCase`, closes Mockery in `tearDown`), loaded via the `autoload-dev` classmap. Test files use that global class directly. New tests must end in `Test.php` under `tests/` for `phpunit.xml` (`suffix="Test.php"`) to discover them.
- `composer.lock` is gitignored (present locally for reproducibility, but never committed).
- `phpunit.xml.bak` is a stale PHPUnit 9 config; the live config is `phpunit.xml` (PHPUnit 11). Don't edit the `.bak`.
- `composer.json` allows PHP 8.2 but CI only exercises 8.3+; keep `src/` free of 8.3+-only syntax. CI also runs `php -l` over `src/`.
- The `code:models` command lives at `src/Coders/Console/CodeModelsCommand.php` and supports `--schema` (`-s`), `--connection` (`-c`), `--table` (`-t`), `--view`, `--pg-schema`, and `--dry-run` (lists output without writing). `--pg-schema` resolution order: option -> `DB_SCHEMA` env -> connection config -> `public`.
- `config/models.php` is the single source of generation behavior (casts, ordering, naming strategies, per-model overrides). Per-table blocks must be **root-level siblings of `'*'`** — nesting them inside `'*'` is silently ignored. Connection overrides use the `@connections` key (not `connections`), and table-level `casts` are merged on top of global patterns. `docs/improvements.md`, `docs/roadmap.md`, and `ENHANCEMENTS.md` track implemented/planned work — check them before adding a feature that may already exist. `docs/custom-schema-mappers.md` covers connection-class mapping.
- Default integration branch is `develop`. After a green `UnitTests` run, `pr-automation` auto-approves the PR using a GitHub App token (`vars.CLIENT_ID` + `secrets.APP_PRIVATE_KEY`). Pushing to `develop` auto-opens a "Next release" PR to `main`; pushing to `main` bumps the `v*` tag and drafts a GitHub release (`bump-and-tag`). `pr-agent.yml` also runs Codium PR-Agent on PRs.
