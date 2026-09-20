# Feature & Improvement Roadmap

Prioritized roadmap for `masgeek/reliese-laravel-model-gen`.

- **Priority:** P0 (fix/do next) → P1 (short-term) → P2 (strategic / later)
- **Value:** user-facing impact for the typical model-generation workflow
- **Effort:** <1d (trivial), 1–3d (small), 1–2w (medium), >2w (large)
- Links: existing trackers are `ENHANCEMENTS.md` (action plan) and `docs/improvements.md` (backlog).

| Priority | Value | Effort | Feature / Improvement | Area | Status |
|---|---|---|---|---|---|
| P0 | High | <1d | Deduplicate FK constraints (keyed by constraint name) + schema-scope Postgres introspection | Introspection | ✅ Done (this session) — `fillRelations()` dedupes `(column, referenced)` pairs; the `pg_constraint`/`pg_indexes` queries now filter by `pg_namespace`/`schemaname` |
| P0 | High | 1–3d | Guard `BelongsTo::body()` against repeated `where()` for the same column pair | Codegen | ✅ Done (this session) — one `->where()` per distinct pair; single-column FKs emit none |
| P0 | High | 1–3d | Live SQLite introspection tests (in-memory DB, `pdo_sqlite`) — real `fillRelations`/`fillColumns` assertions | Tests | 🙏 New — CI already installs `pdo_sqlite`; huge coverage win with near-zero setup |
| P0 | High | 1–3d | `--all-connections` / comma-separated `--connection` for one-run multi-connection generation | Command | Open (`docs/improvements.md` #3) |
| P0 | Medium | <1d | Validate `--connection` / `--schema` against `database.connections` before booting the mapper (actionable error instead of generic Laravel exception) | Command | Open (`docs/improvements.md`) |
| P0 | Medium | 1–3d | Cache resolved related models in `Factory::makeModel()` — `Model::fill()`/`ReferenceFactory` rebuild the same related Model per relation (N×M work on wide schemas) | Perf | 🙏 New — no behavior change, repeatable wins |
| P1 | High | 1–3d | Collision-safe relation names — two FKs to the same table currently overwrite silently (`$relations[$name] = $relation`) | Relations | 🙏 New — surface the TODO in `properties()` ("Handle collisions") instead of dropping a relation |
| P1 | High | 1–3d | `strict_types` config option (`declare(strict_types=1)` in generated files) | Codegen | Open (`ENHANCEMENTS.md`) |
| P1 | High | 1–2w | `HasManyThrough` / `HasOneThrough` detection (3-table chains) | Relations | Open (`ENHANCEMENTS.md`) |
| P1 | Medium | 1–3d | PHP 8.1 backed-enum casts on `ENUM` columns | Codegen | Open (`ENHANCEMENTS.md`) |
| P1 | Medium | <1d | Configurable nullable style (`string|null` vs `?string`) | Codegen | Open (`ENHANCEMENTS.md`) |
| P1 | High | 1–3d | Per-model `casts` overrides — merge table/schema/connection-level `casts` on top of the global ones (first-match-wins → layered) | Codegen | ✅ Done — `Config::get()` now merges `casts` across the resolution tree |
| P1 | Medium | 1–3d | Clarify Postgres `Schema::schemas()` — it enumerates **databases** (`pg_database`), not namespaces; align with `--pg-schema` semantics | Introspection | 🙏 New — surprising behavior worth a decision (document vs fix) |
| P1 | Low | 1–3d | Tests for `SchemaManager::register()/getMapper()` fallback + `custom_mappers` wiring | Tests | Open (`docs/improvements.md` #6) |
| P2 | High | >2w | Migrate MySQL/Postgres introspection from raw SQL / `SHOW CREATE TABLE` regex to Doctrine DBAL (parity with SQLite) | Introspection | Deferred (`docs/improvements.md`) |
| P2 | Medium | 1–2w | Schema-driver integration tests for MySQL/Postgres (containerized or doctrine-fixture based) | Tests | Open (`docs/improvements.md`) |
| P2 | Low | 1–3d | Config-driven generation order (DB order vs alphabetical) | Command | 🙏 New — complements the `feat/alphabetical-generation` branch |
| P2 | Low | <1d | Reconcile stale backlog claims with `composer.json` (docs still say `doctrine/dbal >=2.5`; file requires `^3.0|^4.0`) | Docs | 🙏 New |

Priorities assume the target user is a Laravel dev generating from a live Postgres/MySQL schema; adjust if your pain points differ.