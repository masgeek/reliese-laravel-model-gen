<?php

use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relations\BelongsTo;
use Reliese\Meta\Blueprint;

class ModelTest extends TestCase
{
    public static function dataForTestPhpTypeHint()
    {
        return [
            'Non-nullable int' => [
                'castType' => 'int',
                'nullable' => false,
                'expect' => 'int',
            ],
            'Nullable int' => [
                'castType' => 'int',
                'nullable' => true,
                'expect' => 'int|null',
            ],
            'Non-nullable json' => [
                'castType' => 'json',
                'nullable' => false,
                'expect' => 'array',
            ],
            'Nullable json' => [
                'castType' => 'json',
                'nullable' => true,
                'expect' => 'array|null',
            ],
            'Non-nullable date' => [
                'castType' => 'date',
                'nullable' => false,
                'expect' => '\Carbon\Carbon',
            ],
            'Nullable date' => [
                'castType' => 'date',
                'nullable' => true,
                'expect' => '\Carbon\Carbon|null',
            ],
        ];
    }

    #[DataProvider('dataForTestPhpTypeHint')]
    public function testPhpTypeHint($castType, $nullable, $expect)
    {
        $model = new Model(
            new Blueprint('test', 'test', 'test'),
            new Factory(
                \Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                \Mockery::mock(Illuminate\Filesystem\Filesystem::class),
                \Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config(['*' => ['parent' => \Illuminate\Database\Eloquent\Model::class]])
            )
        );

        $result = $model->phpTypeHint($castType, $nullable);
        $this->assertSame($expect, $result);
    }

    #[DataProvider('provideDataForTestNullableRelationships')]
    public function testBelongsToNullableRelationships($nullable, $expectedTypehint)
    {
        $columnDefinition = new Fluent(
            [
                'nullable' => $nullable,
            ]
        );

        $baseBlueprint = Mockery::mock(Blueprint::class);
        $baseBlueprint->shouldReceive('columns')->andReturn([$columnDefinition]);
        $baseBlueprint->shouldReceive('schema')->andReturn('test');
        $baseBlueprint->shouldReceive('qualifiedTable')->andReturn('test.test');
        $baseBlueprint->shouldReceive('connection')->andReturn('test');
        $baseBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => []]));
        $baseBlueprint->shouldReceive('relations')->andReturn([]);
        $baseBlueprint->shouldReceive('table')->andReturn('things');
        $baseBlueprint->shouldReceive('column')->andReturn($columnDefinition);
        $baseBlueprint->shouldReceive('isView')->andReturn(false);

        $model = new Model(
            $baseBlueprint,
            new Factory(
                \Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                \Mockery::mock(Illuminate\Filesystem\Filesystem::class),
                \Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config(['*' => ['parent' => \Illuminate\Database\Eloquent\Model::class]])
            )
        );

        $relation = new BelongsTo(
            new Fluent([
                'columns' => [
                    $columnDefinition
                ]
            ]),
            $model,
            $model
        );

        $this->assertSame($expectedTypehint, $relation->hint());
    }

    public static function provideDataForTestNullableRelationships()
    {
        return [
            'Nullable Relation' => [
                true, '\\\\Thing|null'
            ],
            'Non Nullable Relation' => [
                false, '\\\\Thing'
            ]
        ];
    }
}
