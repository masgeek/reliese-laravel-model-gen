<?php

use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Reliese\Coders\Model\Config;
use Reliese\Coders\Model\Factory;
use Reliese\Meta\Blueprint;
use Reliese\Support\Classify;

class OrderableFactory extends Factory
{
    public function order(array $tables, $order)
    {
        return $this->orderTables($tables, $order);
    }
}

class FactoryOrderTest extends TestCase
{
    private function makeFactory(): OrderableFactory
    {
        return new OrderableFactory(
            Mockery::mock(DatabaseManager::class),
            Mockery::mock(Filesystem::class),
            Mockery::mock(Classify::class),
            new Config([])
        );
    }

    /**
     * @param string[] $tables
     *
     * @return \Reliese\Meta\Blueprint[]
     */
    private function blueprints(array $tables): array
    {
        return array_map(function ($table) {
            return new Blueprint('test', 'test', $table);
        }, $tables);
    }

    private function tableNames(array $blueprints): array
    {
        return array_map(function (Blueprint $blueprint) {
            return $blueprint->table();
        }, $blueprints);
    }

    public function testAlphabeticalIsTheDefaultOrder(): void
    {
        $factory = $this->makeFactory();

        $ordered = $factory->order(
            $this->blueprints(['zeta', 'alpha', 'middle']),
            'alphabetical'
        );

        $this->assertSame(['alpha', 'middle', 'zeta'], $this->tableNames($ordered));
    }

    public function testDatabaseOrderKeepsTheProvidedSequence(): void
    {
        $factory = $this->makeFactory();

        $ordered = $factory->order(
            $this->blueprints(['users', 'logs', 'teams']),
            'database'
        );

        $this->assertSame(['users', 'logs', 'teams'], $this->tableNames($ordered));
    }

    public function testArrayOrderAppliesTheExplicitSequence(): void
    {
        $factory = $this->makeFactory();

        $ordered = $factory->order(
            $this->blueprints(['logs', 'users', 'teams']),
            ['users', 'teams']
        );

        $this->assertSame(['users', 'teams', 'logs'], $this->tableNames($ordered));
    }

    public function testUnlistedTablesFollowTheExplicitSequenceAlphabetically(): void
    {
        $factory = $this->makeFactory();

        $ordered = $factory->order(
            $this->blueprints(['zeta', 'users', 'alpha', 'logs']),
            ['users', 'logs']
        );

        $this->assertSame(['users', 'logs', 'alpha', 'zeta'], $this->tableNames($ordered));
    }
}