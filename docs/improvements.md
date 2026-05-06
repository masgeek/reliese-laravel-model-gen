# Improvements Backlog

Tracks identified improvements, their status, and implementation notes.

---

## Schema Mapper System

- [x] **#1 — Inheritance-based mapper fallback**
  `SchemaManager::hasMapping()` and `getMapper()` now walk registered entries with `instanceof` after an exact-class miss. A connection class that extends a registered base (e.g. a PgBouncer wrapper extending `PostgresConnection`) resolves automatically with no config needed.
  _Implemented in `src/Meta/SchemaManager.php`_

- [x] **#2 — Actionable error message when no mapper is found**
  The `RuntimeException` in `SchemaManager::boot()` now tells the user exactly which config key to add (`custom_mappers` in `config/models.php`) and lists the three built-in mapper class names.
  _Implemented in `src/Meta/SchemaManager.php`_

- [x] **#4 — Validate custom mappers at registration time**
  `SchemaManager::register()` now checks that the mapper class exists and implements `Reliese\Meta\Schema`, throwing an `InvalidArgumentException` at service-provider boot rather than silently accepting a bad value.
  _Implemented in `src/Meta/SchemaManager.php` and `src/Coders/CodersServiceProvider.php`_

- [x] **Config-driven custom mapper registration**
  Added `custom_mappers` top-level key to `config/models.php`. The service provider reads it at boot and calls `SchemaManager::register()` for each entry.
  _Implemented in `config/models.php` and `src/Coders/CodersServiceProvider.php`_

---

## Artisan Command

- [ ] **#3 — Multi-connection single run**
  Add an `--all-connections` flag (or allow comma-separated values for `--connection`) so users with multiple database connections can generate all models in one command instead of running `code:models` repeatedly.

- [ ] **Improve `--connection` / `--schema` validation**
  Validate that the specified connection exists in `database.connections` before attempting to boot the `SchemaManager`. Currently an invalid name produces a generic Laravel exception with no helpful context.

- [ ] **Fix option descriptions to mention all supported drivers**
  The `--schema` option description mentions only MySQL but the option is meaningful for Postgres (schema namespaces) and SQLite as well.

---

## Schema Driver Parity

- [x] **#5 — View model generation for MySQL and SQLite**
  On investigation, view introspection was already implemented in both MySQL (`SHOW FULL TABLES WHERE Table_type='VIEW'`) and SQLite (`sqlite_master WHERE type='view'`). Materialized views remain Postgres-only, which is correct as they are a PostgreSQL-specific feature.

- [x] **Named foreign key constraint names across all drivers**
  All three drivers now populate the `index` field of each relation Fluent with the actual constraint name from the database, instead of always `''`:
  - MySQL: Updated `fillRelations` regex to `CONSTRAINT name FOREIGN KEY (...)` — captures `$setup[1]` as the name.
  - Postgres: `$constraintName` (from `pg_constraint.conname`) now flows into the `index` field via the `foreach ($fk as $constraintName => $row)` key.
  - SQLite: Uses Doctrine's `$setup->getName()`.
  _Implemented in `src/Meta/MySql/Schema.php`, `src/Meta/Postgres/Schema.php`, `src/Meta/Sqlite/Schema.php`_

- [x] **Named primary key index field**
  The `index` field in the primary key Fluent was always `''`. Now:
  - MySQL: Set to the literal string `'PRIMARY'` (MySQL's fixed constraint name).
  - Postgres: Set to `pg_constraint.conname` for type `'p'` (e.g., `users_pkey`).
  - SQLite: Set to Doctrine's `$primary->getName()`.
  _Implemented in `src/Meta/MySql/Schema.php`, `src/Meta/Postgres/Schema.php`, `src/Meta/Sqlite/Schema.php`_

- [ ] **Postgres: switch constraint discovery to Doctrine** _(deferred)_
  The Postgres driver uses raw SQL against system catalogs where SQLite uses Doctrine DBAL. Migrating would improve consistency but is a large refactor with risk of regression, particularly given the loose `doctrine/dbal >=2.5` constraint spanning two major API versions. Deferred until test infrastructure improves.

---

## Test Coverage

- [ ] **#6 — Unit tests for `SchemaManager` mapper registration**
  No tests exist for `register()`, `hasMapping()`, `getMapper()`, or the new inheritance fallback. Should cover: exact match, inheritance match, missing mapper, invalid mapper class, non-`Schema` mapper class.

- [ ] **Tests for the new `custom_mappers` config wiring**
  The `CodersServiceProvider::registerCustomMappers()` path has no test coverage. A feature test that boots a minimal Laravel app with a `custom_mappers` config entry would catch regressions.

- [ ] **Integration tests for Postgres and SQLite schema drivers**
  `ConfigTest` is the only well-covered area. The schema introspection drivers have no test coverage; bugs in column-type mapping or constraint discovery go undetected until runtime.

---

## Code Modernisation

- [ ] **#7 — PHP 8.x constructor property promotion**
  `Factory`, `SchemaManager`, `CodeModelsCommand`, and several Meta classes use old-style multi-line constructor assignment. Migrating to promoted properties would reduce boilerplate — requires bumping the minimum PHP version to 8.0.

- [ ] **`match()` for mapper resolution**
  The static `$lookup` array + `instanceof` fallback in `SchemaManager` is a natural candidate for a `match()` expression once PHP 8.0 is the floor.

- [ ] **Composer constraint tightening**
  - `doctrine/dbal` constraint (`>=2.5`) is too loose; pin to `^2.13|^3.0`
  - `phpunit/phpunit` dev dependency is on `^9` (2020); upgrade to `^10` or `^11`
  - `fzaninotto/faker` (`~1.4`, archived) should be replaced with `fakerphp/faker`
