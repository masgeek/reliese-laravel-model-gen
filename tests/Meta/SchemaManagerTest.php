<?php

use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reliese\Meta\Blueprint;
use Reliese\Meta\Schema;
use Reliese\Meta\SchemaManager;

// ---------------------------------------------------------------------------
// Stub connection classes for inheritance-resolution tests.
// Constructors are overridden so no real PDO is needed.
//
// StubUnregisteredConnection extends the abstract Connection base — it has
// no relationship to any of the built-in registered drivers (MySQL/Postgres/
// SQLite/MariaDB), so hasMapping() should return false for it by default.
//
// StubBaseConnection extends PostgresConnection so that the "custom mapper
// beats built-in parent" test can verify the most-derived-wins logic against
// a driver that IS already registered.
// ---------------------------------------------------------------------------

class StubUnregisteredConnection extends \Illuminate\Database\Connection
{
    public function __construct() {}

    // Satisfy the abstract method required by Connection.
    protected function getDefaultQueryGrammar(): \Illuminate\Database\Query\Grammars\Grammar
    {
        return new \Illuminate\Database\Query\Grammars\Grammar();
    }
}

class StubBaseConnection extends \Illuminate\Database\PostgresConnection
{
    public function __construct() {}
}

class StubChildConnection extends StubBaseConnection {}

class StubGrandchildConnection extends StubChildConnection {}

// ---------------------------------------------------------------------------
// A minimal Schema implementation used as a valid mapper in registration tests.
// ---------------------------------------------------------------------------

class ValidFakeSchema implements Schema
{
    public function __construct($schema, ConnectionInterface $connection) {}

    public static function schemas(ConnectionInterface $connection): array
    {
        return [];
    }

    public function connection(): ConnectionInterface
    {
        return \Mockery::mock(ConnectionInterface::class);
    }

    public function schema(): string { return ''; }
    public function tables(): array { return []; }
    public function has($table): bool { return false; }
    public function table($table): Blueprint { return new Blueprint('', '', $table); }
    public function referencing(Blueprint $table): array { return []; }
}

// A second valid mapper used to distinguish "which one wins" in resolution tests.
class AnotherFakeSchema extends ValidFakeSchema {}

// ---------------------------------------------------------------------------
// Testable subclass that skips boot() so no connection is needed for unit tests.
// ---------------------------------------------------------------------------

class TestableSchemaManager extends SchemaManager
{
    public function __construct()
    {
        // Intentionally skip parent constructor (no boot, no connection required).
    }

    public function withConnection(object $connection): static
    {
        $ref = new ReflectionProperty(SchemaManager::class, 'connection');
        $ref->setAccessible(true);
        $ref->setValue($this, $connection);
        return $this;
    }

    public function publicHasMapping(): bool
    {
        return $this->hasMapping();
    }

    public function publicGetMapper(): string
    {
        return $this->getMapper();
    }
}

// ---------------------------------------------------------------------------

class SchemaManagerTest extends TestCase
{
    /** @var array Original $lookup saved before each test */
    private array $originalLookup;

    protected function setUp(): void
    {
        $ref = new ReflectionProperty(SchemaManager::class, 'lookup');
        $ref->setAccessible(true);
        $this->originalLookup = $ref->getValue();
    }

    protected function tearDown(): void
    {
        // Restore the static lookup so registered entries don't bleed between tests.
        $ref = new ReflectionProperty(SchemaManager::class, 'lookup');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->originalLookup);

        \Mockery::close();
    }

    // -----------------------------------------------------------------------
    // register() – validation
    // -----------------------------------------------------------------------

    public function testRegisterThrowsForNonExistentMapperClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        SchemaManager::register(StubBaseConnection::class, 'NonExistent\Schema\Class');
    }

    public function testRegisterThrowsForMapperThatDoesNotImplementSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        SchemaManager::register(StubBaseConnection::class, \stdClass::class);
    }

    public function testRegisterSucceedsWithValidMapper(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);

        $ref = new ReflectionProperty(SchemaManager::class, 'lookup');
        $ref->setAccessible(true);
        $lookup = $ref->getValue();

        $this->assertArrayHasKey(StubBaseConnection::class, $lookup);
        $this->assertSame(ValidFakeSchema::class, $lookup[StubBaseConnection::class]);
    }

    // -----------------------------------------------------------------------
    // hasMapping() – exact match
    // -----------------------------------------------------------------------

    public function testHasMappingReturnsTrueForExactMatch(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);

        $manager = (new TestableSchemaManager())->withConnection(new StubBaseConnection());

        $this->assertTrue($manager->publicHasMapping());
    }

    public function testHasMappingReturnsFalseForUnregisteredConnection(): void
    {
        // StubUnregisteredConnection extends the abstract Connection base only —
        // it has no relationship to any registered driver, so hasMapping() must
        // return false without any prior register() call.
        $manager = (new TestableSchemaManager())->withConnection(new StubUnregisteredConnection());

        $this->assertFalse($manager->publicHasMapping());
    }

    // -----------------------------------------------------------------------
    // hasMapping() – inheritance fallback
    // -----------------------------------------------------------------------

    public function testHasMappingReturnsTrueViaInheritanceFallback(): void
    {
        // Register only the parent; use a child connection.
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);

        $manager = (new TestableSchemaManager())->withConnection(new StubChildConnection());

        $this->assertTrue($manager->publicHasMapping());
    }

    public function testHasMappingReturnsTrueViaDeepInheritanceFallback(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);

        $manager = (new TestableSchemaManager())->withConnection(new StubGrandchildConnection());

        $this->assertTrue($manager->publicHasMapping());
    }

    // -----------------------------------------------------------------------
    // getMapper() – exact match
    // -----------------------------------------------------------------------

    public function testGetMapperReturnsExactMatchBeforeInheritanceFallback(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);
        SchemaManager::register(StubChildConnection::class, AnotherFakeSchema::class);

        // Use StubBaseConnection exactly — should get ValidFakeSchema, not AnotherFakeSchema.
        $manager = (new TestableSchemaManager())->withConnection(new StubBaseConnection());

        $this->assertSame(ValidFakeSchema::class, $manager->publicGetMapper());
    }

    // -----------------------------------------------------------------------
    // getMapper() – inheritance fallback
    // -----------------------------------------------------------------------

    public function testGetMapperFallsBackToInheritedMapperWhenNoExactMatch(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);

        // StubChildConnection has no exact entry — should inherit from StubBaseConnection.
        $manager = (new TestableSchemaManager())->withConnection(new StubChildConnection());

        $this->assertSame(ValidFakeSchema::class, $manager->publicGetMapper());
    }

    // -----------------------------------------------------------------------
    // getMapper() – most-derived-wins
    // -----------------------------------------------------------------------

    public function testGetMapperPrefersMoreDerivedRegisteredClassOverBuiltInParent(): void
    {
        // Both StubBaseConnection and StubChildConnection are registered.
        // Using StubChildConnection → most-derived match should win.
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);
        SchemaManager::register(StubChildConnection::class, AnotherFakeSchema::class);

        $manager = (new TestableSchemaManager())->withConnection(new StubChildConnection());

        $this->assertSame(AnotherFakeSchema::class, $manager->publicGetMapper());
    }

    public function testGetMapperPrefersGrandchildOverParentAndBase(): void
    {
        SchemaManager::register(StubBaseConnection::class, ValidFakeSchema::class);
        SchemaManager::register(StubChildConnection::class, AnotherFakeSchema::class);

        // StubGrandchildConnection only matches via StubChildConnection and StubBaseConnection.
        // StubChildConnection is more derived → AnotherFakeSchema wins.
        $manager = (new TestableSchemaManager())->withConnection(new StubGrandchildConnection());

        $this->assertSame(AnotherFakeSchema::class, $manager->publicGetMapper());
    }

    public function testCustomMapperBeatsBuiltInPostgresConnectionWhenChildIsRegistered(): void
    {
        // Simulate the PgBouncer use-case: a wrapper that extends PostgresConnection,
        // registered explicitly. Should beat the built-in PostgresConnection entry.
        SchemaManager::register(StubBaseConnection::class, AnotherFakeSchema::class);

        // StubBaseConnection extends PostgresConnection which IS in the built-in lookup.
        // The registered StubBaseConnection entry is more derived → should win.
        $manager = (new TestableSchemaManager())->withConnection(new StubBaseConnection());

        $this->assertSame(AnotherFakeSchema::class, $manager->publicGetMapper());
    }
}
