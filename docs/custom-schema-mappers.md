# Custom Schema Mappers

This document describes the custom schema mapper system: what it is, when you need it, and how to use it.

---

## Background

Reliese maps each Laravel database connection class to a **schema mapper** — a class that knows how to introspect that database's tables, columns, and constraints. The built-in mappings are:

| Connection class | Mapper |
|---|---|
| `Illuminate\Database\MySqlConnection` | `Reliese\Meta\MySql\Schema` |
| `Illuminate\Database\MariaDbConnection` | `Reliese\Meta\MySql\Schema` |
| `Illuminate\Database\PostgresConnection` | `Reliese\Meta\Postgres\Schema` |
| `Illuminate\Database\SQLiteConnection` | `Reliese\Meta\Sqlite\Schema` |
| `Larapack\DoctrineSupport\Connections\MySqlConnection` | `Reliese\Meta\MySql\Schema` |
| `Staudenmeir\LaravelCte\Connections\MySqlConnection` | `Reliese\Meta\MySql\Schema` |

---

## When you need a custom mapper

If your application uses a third-party or custom connection class (for example a PgBouncer wrapper, a read/write splitting decorator, or any other driver-wrapping package), you will see this error:

```
There is no Schema Mapper registered for [Vendor\Package\SomeConnection] connection.

Add a 'custom_mappers' entry to config/models.php mapping this class to a
built-in mapper (Reliese\Meta\MySql\Schema, Reliese\Meta\Postgres\Schema,
or Reliese\Meta\Sqlite\Schema).
```

---

## Automatic inheritance resolution

Before throwing the error, Reliese checks whether the connection object is an **instance of** any registered connection class. This means if your custom connection extends `Illuminate\Database\PostgresConnection`, it will be resolved automatically with no configuration needed.

When multiple registered entries match via `instanceof` (for example both `MySqlConnection` and a custom `App\TenantConnection extends MySqlConnection` are registered), Reliese picks the **most-derived** match — the registered class that has no other matching registered class as a subclass of it. This ensures a custom mapper registered for `App\TenantConnection` always wins over the built-in `MySqlConnection` entry, regardless of registration order.

---

## Registering a custom mapper via config

Add a top-level `custom_mappers` key to `config/models.php`:

```php
// config/models.php

return [

    '*' => [
        // ... your existing config ...
    ],

    'custom_mappers' => [
        // PgBouncer connection wrapper → use the built-in Postgres mapper
        \Vermaysha\PgbouncerLaravelExtension\PostgresPGBouncerExtension::class
            => \Reliese\Meta\Postgres\Schema::class,

        // Any MySQL-compatible custom connection
        // \YourVendor\YourPackage\CustomMySqlConnection::class
        //     => \Reliese\Meta\MySql\Schema::class,
    ],

];
```

Reliese validates each entry at boot time:

- The mapper class must **exist** — a typo will throw an `InvalidArgumentException` immediately rather than silently doing nothing.
- The mapper class must **implement `Reliese\Meta\Schema`** — passing an arbitrary class produces a clear error with the list of valid built-in options.

---

## Registering a custom mapper programmatically

You can also call `SchemaManager::register()` directly, for example inside a service provider:

```php
use Reliese\Meta\SchemaManager;
use Reliese\Meta\Postgres\Schema as PostgresSchema;
use Vermaysha\PgbouncerLaravelExtension\PostgresPGBouncerExtension;

public function register(): void
{
    SchemaManager::register(PostgresPGBouncerExtension::class, PostgresSchema::class);
}
```

This is equivalent to the config approach and has the same validation.

---

## Writing your own mapper

If no built-in mapper fits your database driver, implement `Reliese\Meta\Schema`:

```php
namespace App\Database\Meta;

use Reliese\Meta\Schema;
use Reliese\Meta\Blueprint;
use Illuminate\Database\ConnectionInterface;

class MyCustomSchema implements Schema
{
    public function __construct(string $schema, ConnectionInterface $connection)
    {
        // ...
    }

    public static function schemas(ConnectionInterface $connection): array
    {
        // Return array of schema/database names available on this connection
    }

    public function connection(): ConnectionInterface { /* ... */ }
    public function schema(): string { /* ... */ }
    public function tables(): array { /* ... */ }
    public function has(string $table): bool { /* ... */ }
    public function table(string $table): Blueprint { /* ... */ }
    public function referencing(Blueprint $table): array { /* ... */ }
}
```

Then register it:

```php
'custom_mappers' => [
    \App\Database\MyCustomConnection::class => \App\Database\Meta\MyCustomSchema::class,
],
```

---

## Summary of changes

| # | Improvement | Detail |
|---|---|---|
| 1 | Inheritance-based fallback | If a connection class is not found by exact name, Reliese walks registered keys with `instanceof` before failing |
| 2 | Most-derived-wins fallback | Among all `instanceof` matches, the most-derived registered class wins — a custom `App\TenantConnection` mapper beats the built-in `MySqlConnection` entry |
| 3 | Actionable error message | The "no mapper" error now tells you exactly which config key to add and which built-in mappers are available |
| 4 | Config-driven registration | `custom_mappers` in `config/models.php` is read at service-provider boot — no code changes needed |
| 5 | Validation at registration | Both mapper existence and `Schema` interface compliance are checked immediately, with clear error messages |
