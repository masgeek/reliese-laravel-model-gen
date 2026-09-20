<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Config;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;

class PerModelCastsTest extends TestCase
{
    private function makeModel(string $table, array $config): Model
    {
        $blueprint = new Blueprint('test', 'test', $table);
        $blueprint->withColumn(new Fluent([
            'name' => 'password',
            'type' => 'string',
            'nullable' => true,
        ]));
        $blueprint->withColumn(new Fluent([
            'name' => 'payload_json',
            'type' => 'string',
            'nullable' => true,
        ]));

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            Mockery::mock(\Reliese\Support\Classify::class),
            new Config($config)
        );

        return new Model($blueprint, $factory);
    }

    public function testTableLevelCastOverridesGlobalForThatModelOnly(): void
    {
        $config = [
            '*' => [
                'parent' => \Illuminate\Database\Eloquent\Model::class,
                'casts' => ['password' => 'hashed'],
            ],
            'api_keys' => [
                'casts' => ['password' => 'encrypted'],
            ],
        ];

        $this->assertSame('encrypted', $this->makeModel('api_keys', $config)->getCasts()['password']);
        $this->assertSame('hashed', $this->makeModel('users', $config)->getCasts()['password']);
    }

    public function testTableLevelCastsLayerOnTopOfGlobalPatterns(): void
    {
        $config = [
            '*' => [
                'parent' => \Illuminate\Database\Eloquent\Model::class,
                'casts' => ['*_json' => 'json'],
            ],
            'api_keys' => [
                'casts' => ['password' => 'encrypted'],
            ],
        ];

        $casts = $this->makeModel('api_keys', $config)->getCasts();

        $this->assertSame('encrypted', $casts['password']);
        // The global pattern still applies to other columns of the same model.
        $this->assertSame('json', $casts['payload_json']);
    }
}