<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Config;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;

class FillablePrimaryKeysTest extends TestCase
{
    private function makeModel(string $table, array $config, array $primaryKey = ['type'], array $columns = ['type', 'label']): Model
    {
        $blueprint = new Blueprint('test', 'test', $table);

        foreach ($columns as $name) {
            $blueprint->withColumn(new Fluent([
                'name' => $name,
                'type' => 'string',
                'nullable' => false,
            ]));
        }

        $blueprint->withPrimaryKey(new Fluent(['columns' => $primaryKey]));

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            Mockery::mock(\Reliese\Support\Classify::class),
            new Config($config)
        );

        return new Model($blueprint, $factory);
    }

    public function testPrimaryKeyIsGuardedByDefault(): void
    {
        $model = $this->makeModel('fertilizer_types', [
            '*' => ['parent' => \Illuminate\Database\Eloquent\Model::class],
        ]);

        $this->assertSame(['label'], $model->getFillable());
    }

    public function testPrimaryKeyCanBeMadeFillableForOneTable(): void
    {
        $model = $this->makeModel('fertilizer_types', [
            '*' => ['parent' => \Illuminate\Database\Eloquent\Model::class],
            'fertilizer_types' => ['fillable_primary_keys' => true],
        ]);

        $this->assertSame(['type', 'label'], $model->getFillable());
    }

    public function testPrimaryKeyCanBeMadeFillableGlobally(): void
    {
        $model = $this->makeModel('fertilizer_types', [
            '*' => [
                'parent' => \Illuminate\Database\Eloquent\Model::class,
                'fillable_primary_keys' => true,
            ],
        ]);

        $this->assertSame(['type', 'label'], $model->getFillable());
    }

    public function testPrimaryKeyPatternsSelectIndividualColumns(): void
    {
        $model = $this->makeModel('fertilizer_types', [
            '*' => ['parent' => \Illuminate\Database\Eloquent\Model::class],
            'fertilizer_types' => ['fillable_primary_keys' => ['type']],
        ], ['type', 'code'], ['type', 'code', 'label']);

        $this->assertSame(['type', 'label'], $model->getFillable());
    }

    public function testExplicitGuardedPatternStillWins(): void
    {
        $model = $this->makeModel('fertilizer_types', [
            '*' => ['parent' => \Illuminate\Database\Eloquent\Model::class],
            'fertilizer_types' => [
                'fillable_primary_keys' => true,
                'guarded' => ['type'],
            ],
        ]);

        $this->assertSame(['label'], $model->getFillable());
    }

    public function testInvalidFillablePrimaryKeysConfigThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fillable_primary_keys');

        $this->makeModel('fertilizer_types', [
            '*' => [
                'parent' => \Illuminate\Database\Eloquent\Model::class,
                'fillable_primary_keys' => 'yes',
            ],
        ]);
    }
}
