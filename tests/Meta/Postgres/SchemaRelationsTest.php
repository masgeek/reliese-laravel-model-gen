<?php

use Reliese\Meta\Blueprint;
use Reliese\Meta\Postgres\Schema;

/**
 * Testable Postgres Schema subclass.
 *
 * The real constructor hits the database via load(); this variant lets us
 * drive fillRelations()/fillConstraints() with mock data instead.
 */
class PostgresSchemaRelationsTestSchema extends Schema
{
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->schema = 'public';
        $this->schema_database = 'public';
    }

    public function exposeFillRelations(array $relations, Blueprint $blueprint): void
    {
        $this->fillRelations($relations, $blueprint);
    }

    public function exposeFillConstraints(Blueprint $blueprint): void
    {
        $this->fillConstraints($blueprint);
    }
}

class PostgresSchemaRelationsTest extends TestCase
{
    private function row(string $column, string $reference, string $constraint = 'fk_bill_notification'): array
    {
        return [
            'attname' => $column,
            'contype' => 'f',
            'conname' => $constraint,
            'parent_table' => 'bill_notifications',
            'parent_attname' => $reference,
        ];
    }

    public function testSingleColumnForeignKeyDuplicatesAreDeduplicated(): void
    {
        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $schema = new PostgresSchemaRelationsTestSchema(Mockery::mock('Illuminate\Database\PostgresConnection'));

        // The same single-column FK reported five times must collapse into one.
        $rows = array_fill(0, 5, $this->row('bill_notification_id', 'id'));

        $schema->exposeFillRelations($rows, $blueprint);

        $relations = $blueprint->relations();
        $this->assertCount(1, $relations);
        $this->assertSame(['bill_notification_id'], $relations[0]->columns);
        $this->assertSame(['id'], $relations[0]->references);
    }

    public function testCompositeForeignKeyKeepsOneEntryPerDistinctColumnPair(): void
    {
        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $schema = new PostgresSchemaRelationsTestSchema(Mockery::mock('Illuminate\Database\PostgresConnection'));

        // A genuinely composite, 2-column FK — each column reported twice
        // (the cross-product the raw query can produce).
        $rows = [
            $this->row('bill_notification_id', 'id'),
            $this->row('branch_code', 'branch_id'),
            $this->row('bill_notification_id', 'id'),
            $this->row('branch_code', 'branch_id'),
        ];

        $schema->exposeFillRelations($rows, $blueprint);

        $relations = $blueprint->relations();
        $this->assertCount(1, $relations);
        $this->assertSame(['bill_notification_id', 'branch_code'], $relations[0]->columns);
        $this->assertSame(['id', 'branch_id'], $relations[0]->references);
    }

    public function testNonForeignKeyRowsAreIgnored(): void
    {
        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $schema = new PostgresSchemaRelationsTestSchema(Mockery::mock('Illuminate\Database\PostgresConnection'));

        $rows = [
            $this->row('bill_notification_id', 'id'),
            ['attname' => 'id', 'contype' => 'p', 'conname' => 'mpesa_transactions_pkey', 'parent_table' => null, 'parent_attname' => null],
        ];

        $schema->exposeFillRelations($rows, $blueprint);

        $relations = $blueprint->relations();
        $this->assertCount(1, $relations);
        $this->assertSame(['bill_notification_id'], $relations[0]->columns);
    }

    public function testConstraintsQueryIsScopedToTheConfiguredSchema(): void
    {
        $captured = [];
        $connection = Mockery::mock('Illuminate\Database\PostgresConnection');
        $connection->shouldReceive('select')->andReturnUsing(function ($sql) use (&$captured) {
            $captured[] = $sql;

            return [];
        });

        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $schema = new PostgresSchemaRelationsTestSchema($connection);

        $schema->exposeFillConstraints($blueprint);

        $this->assertNotEmpty($captured);
        $this->assertStringContainsString('child_class.relnamespace', $captured[0]);
        $this->assertStringContainsString("nspname = 'public'", $captured[0]);
        $this->assertStringContainsString("schemaname = 'public'", $captured[1]);
    }
}