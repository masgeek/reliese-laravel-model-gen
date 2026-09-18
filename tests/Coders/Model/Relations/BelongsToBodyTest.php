<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relations\BelongsTo;
use Reliese\Meta\Blueprint;

class BelongsToBodyTest extends TestCase
{
    /**
     * Builds a real BelongsTo relation against mocked parent/related models.
     *
     * @param string[] $columns    Foreign key columns on the parent table
     * @param string[] $references Referenced columns on the related table
     *
     * @return array [BelongsTo] or [Fluent command, Model parent, Model related]
     */
    private function makeBelongsTo(array $columns, array $references): BelongsTo
    {
        $command = new Fluent([
            'on' => ['public', 'bill_notifications'],
            'columns' => $columns,
            'references' => $references,
        ]);

        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $blueprint->withColumn(new Fluent(['name' => $columns[0], 'nullable' => true]));

        $parent = Mockery::mock(Model::class)->makePartial();
        $parent->shouldReceive('usesPropertyConstants')->andReturn(false);
        $parent->shouldReceive('getTable')->andReturn('mpesa_transactions');
        $parent->shouldReceive('getBlueprint')->andReturn($blueprint);

        $related = Mockery::mock(Model::class)->makePartial();
        $related->shouldReceive('usesPropertyConstants')->andReturn(false);
        $related->shouldReceive('getQualifiedUserClassName')->andReturn('\App\Models\BillNotification');
        $related->shouldReceive('getRecordName')->andReturn('bill_notification');
        $related->shouldReceive('getPrimaryKey')->andReturn('id');
        $related->shouldReceive('getTable')->andReturn('bill_notifications');

        return new BelongsTo($command, $parent, $related);
    }

    public function testSingleColumnForeignKeyDoesNotEmitWhereClause(): void
    {
        $relationship = $this->makeBelongsTo(['bill_notification_id'], ['id']);

        $this->assertStringNotContainsString('->where(', $relationship->body());
        $this->assertStringContainsString(
            'belongsTo(\App\Models\BillNotification::class',
            $relationship->body()
        );
    }

    public function testCompositeForeignKeyEmitsExactlyOneWherePerColumn(): void
    {
        $relationship = $this->makeBelongsTo(
            ['bill_notification_id', 'branch_code'],
            ['id', 'branch_id']
        );

        $body = $relationship->body();

        // Two columns → one where() per column, and no duplicates.
        $this->assertSame(2, substr_count($body, '->where('));
        $this->assertSame(1, substr_count(
            $body,
            "'bill_notifications.id', '=', 'mpesa_transactions.bill_notification_id'"
        ));
        $this->assertSame(1, substr_count(
            $body,
            "'bill_notifications.branch_id', '=', 'mpesa_transactions.branch_code'"
        ));
    }

    public function testRepeatedColumnPairsDoNotMultiplyWhereClauses(): void
    {
        // Simulates introspection returning the same single-column FK five times,
        // e.g. because the constraint query was not schema-scoped.
        $command = new Fluent([
            'on' => ['public', 'bill_notifications'],
            'columns' => array_fill(0, 5, 'bill_notification_id'),
            'references' => array_fill(0, 5, 'id'),
        ]);

        $blueprint = new Blueprint('pgsql', 'public', 'mpesa_transactions');
        $blueprint->withColumn(new Fluent(['name' => 'bill_notification_id', 'nullable' => true]));

        $parent = Mockery::mock(Model::class)->makePartial();
        $parent->shouldReceive('usesPropertyConstants')->andReturn(false);
        $parent->shouldReceive('getTable')->andReturn('mpesa_transactions');
        $parent->shouldReceive('getBlueprint')->andReturn($blueprint);

        $related = Mockery::mock(Model::class)->makePartial();
        $related->shouldReceive('usesPropertyConstants')->andReturn(false);
        $related->shouldReceive('getQualifiedUserClassName')->andReturn('\App\Models\BillNotification');
        $related->shouldReceive('getRecordName')->andReturn('bill_notification');
        $related->shouldReceive('getPrimaryKey')->andReturn('id');
        $related->shouldReceive('getTable')->andReturn('bill_notifications');

        $relationship = new BelongsTo($command, $parent, $related);

        // Even if a driver reports the same pair repeatedly, body() must not
        // emit a duplicated where() per occurrence — only one per distinct pair.
        $this->assertSame(1, substr_count($relationship->body(), '->where('));
    }
}