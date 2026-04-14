# Enhancement Action Plan

Tracked improvements for `masgeek/reliese-laravel-model-gen`.  
Check off each item as it is implemented and merged.

---

## Command UX

- [ ] **`--dry-run` flag**
  Add a `--dry-run` option to `code:models` that lists every table/view that would be generated
  (class name + output file path) without writing any files.
  Useful for auditing a schema before the first run.
  _File:_ `src/Coders/Console/CodeModelsCommand.php`, `src/Coders/Model/Factory.php`

- [ ] **Per-table progress output**
  Emit a `$this->line()` message for each table/view as it is processed so large
  databases give visible feedback instead of silent running.
  _File:_ `src/Coders/Console/CodeModelsCommand.php`, `src/Coders/Model/Factory.php`

- [ ] **`--view` CLI flag**
  Add `--view=<name>` as a CLI complement to `--table`, allowing a single view model to
  be generated without enabling `with_views` globally in config.
  Example: `php artisan code:models --view=user_summary`
  _File:_ `src/Coders/Console/CodeModelsCommand.php`

---

## Bug / Correctness

- [x] **Fix PostgreSQL `fetchTables()` signature mismatch**
  `load()` calls `$this->fetchTables($this->schema)` but the method declares no parameters.
  PHP silently ignores the extra argument today, but the discrepancy is misleading and
  masks intent. Align the signature with the MySQL counterpart.
  _File:_ `src/Meta/Postgres/Schema.php`

- [x] **Short-circuit MySQL `fillConstraints()` for views**
  Currently issues `SHOW CREATE TABLE <view>` and runs three regex passes
  (`fillPrimaryKey`, `fillIndexes`, `fillRelations`) that will never match a
  `CREATE VIEW` statement. Guard with an early return when `$blueprint->isView()`.
  _File:_ `src/Meta/MySql/Schema.php`

---

## View Model Improvements

- [x] **Auto-disable writes for view models**
  When generating a model for a view, set `$fillable = []` and emit `$guarded = ['*']`
  so Eloquent refuses mass-assignment on a read-only source.
  _File:_ `src/Coders/Model/Factory.php` (`body()` method), `src/Coders/Model/Model.php`

- [x] **PostgreSQL materialized views support**
  `pg_views` covers only regular views; `pg_matviews` is a separate catalog.
  Add a `fetchMaterializedViews()` method and load them alongside regular views
  when `with_views` is enabled.
  _File:_ `src/Meta/Postgres/Schema.php`

- [x] **View-specific parent class config**
  Allow a `view_parent` config key (parallel to `parent`) so view models can extend
  a different base class — for example a `ReadOnlyModel` that throws on
  `save()` / `delete()` / `update()`.
  _Files:_ `config/models.php`, `src/Coders/Model/Model.php`

---

## Code Generation Quality

- [ ] **`strict_types` config option**
  Add a `'strict_types' => false` config key. When `true`, prepend
  `declare(strict_types=1);` to every generated file.
  No compatibility cost — PHP 7.3+ is already required.
  _Files:_ `config/models.php`, `src/Coders/Model/Factory.php` (`fillTemplate()`)

- [ ] **PHP 8.1 backed enum casts**
  When a MySQL `ENUM` column is detected and a PHP 8.1+ target is configured,
  generate (or reference) a backed enum class and emit the appropriate
  `Casts\Attribute` cast instead of falling back to `string`.
  _Files:_ `src/Meta/MySql/Column.php`, `src/Coders/Model/Model.php` (`parseColumn()`)

- [ ] **Configurable nullable type-hint style**
  `phpTypeHint()` always produces the union style `string|null`.
  Add a `'nullable_style' => 'union'` config option accepting `'union'` (`string|null`)
  or `'question_mark'` (`?string`) to match the target codebase's PHP version and style.
  _Files:_ `config/models.php`, `src/Coders/Model/Model.php` (`phpTypeHint()`)

---

## Relations

- [ ] **`HasManyThrough` / `HasOneThrough` detection**
  The pivot detection in `ReferenceFactory` only produces `BelongsToMany`.
  A three-table chain (e.g. `countries → users → posts`) should generate
  `HasManyThrough` / `HasOneThrough` on the distant end.
  _Files:_ `src/Coders/Model/Relations/ReferenceFactory.php`,
  `src/Coders/Model/Relations/` (new `HasManyThrough.php`, `HasOneThrough.php`)

---

## Summary Checklist

| # | Area | Item | Done |
|---|------|------|------|
| 1 | Command UX | `--dry-run` flag | [ ] |
| 2 | Command UX | Per-table progress output | [ ] |
| 3 | Command UX | `--view` CLI flag | [ ] |
| 4 | Bug | PostgreSQL `fetchTables()` signature | [x] |
| 5 | Bug | MySQL `fillConstraints()` short-circuit for views | [x] |
| 6 | Views | Auto-disable writes for view models | [x] |
| 7 | Views | PostgreSQL materialized views | [x] |
| 8 | Views | View-specific parent class config | [x] |
| 9 | Codegen | `strict_types` config option | [ ] |
| 10 | Codegen | PHP 8.1 enum casts | [ ] |
| 11 | Codegen | Nullable type-hint style config | [ ] |
| 12 | Relations | `HasManyThrough` / `HasOneThrough` | [ ] |
