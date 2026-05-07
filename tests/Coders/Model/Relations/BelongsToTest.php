<?php

use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relations\BelongsTo;

class BelongsToTest extends TestCase
{
    public static function provideForeignKeyStrategyPermutations()
    {
        // usesSnakeAttributes, primaryKey, foreignKey, expected
        return [
            // columns use camelCase
            [false, 'id', 'lineManagerId', 'lineManager'],
            [false, 'Id', 'lineManagerId', 'lineManager'],
            [false, 'ID', 'lineManagerID', 'lineManager'],
            // columns use snake_case
            [true, 'id', 'line_manager_id', 'line_manager'],
            [true, 'ID', 'line_manager_id', 'line_manager'],
            // foreign keys without primary key suffix
            [false, 'id', 'lineManager', 'lineManager'],
            [true, 'id', 'line_manager', 'line_manager'],
        ];
    }

    #[DataProvider('provideForeignKeyStrategyPermutations')]
    public function testNameUsingForeignKeyStrategy($usesSnakeAttributes, $primaryKey, $foreignKey, $expected)
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('getRelationNameStrategy')->andReturn('foreign_key');
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn($usesSnakeAttributes);

        /** @var BelongsTo|\Mockery\Mock $relationship */
        $relationship = Mockery::mock(BelongsTo::class, [$relation, $subject, $relatedModel])->makePartial();
        $relationship->shouldAllowMockingProtectedMethods();
        $relationship->shouldReceive('otherKey')->andReturn($primaryKey);
        $relationship->shouldReceive('foreignKey')->andReturn($foreignKey);

        $this->assertEquals(
            $expected,
            $relationship->name(),
            json_encode(compact('usesSnakeAttributes', 'primaryKey', 'foreignKey'))
        );
    }

    public static function provideRelatedStrategyPermutations()
    {
        // usesSnakeAttributes, relatedClassName, expected
        return [
            [false, 'LineManager', 'lineManager'],
            [true, 'LineManager', 'line_manager'],
        ];
    }

    #[DataProvider('provideRelatedStrategyPermutations')]
    public function testNameUsingRelatedStrategy($usesSnakeAttributes, $relatedClassName, $expected)
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn($relatedClassName);

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('getRelationNameStrategy')->andReturn('related');
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn($usesSnakeAttributes);

        /** @var BelongsTo|\Mockery\Mock $relationship */
        $relationship = Mockery::mock(BelongsTo::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertEquals(
            $expected,
            $relationship->name(),
            json_encode(compact('usesSnakeAttributes', 'relatedClassName'))
        );
    }
}
