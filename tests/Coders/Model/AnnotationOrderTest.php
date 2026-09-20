<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Config;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;
use Reliese\Support\Classify;

class AnnotationOrderFactory extends Factory
{
    public function orderedItems(array $items, $order)
    {
        return $this->orderAnnotations($items, $order);
    }

    public function docProperties(Model $model)
    {
        return $this->properties($model);
    }
}

class AnnotationOrderTest extends TestCase
{
    private function makeFactory(array $config): AnnotationOrderFactory
    {
        return new AnnotationOrderFactory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            new Classify(),
            new Config($config)
        );
    }

    public function testAlphabeticalIsTheDefaultOrder(): void
    {
        $factory = $this->makeFactory(['*' => ['parent' => Model::class]]);

        $ordered = $factory->orderedItems(
            ['role_id' => 'int', 'user_invitation_id' => 'int', 'id' => 'int'],
            'alphabetical'
        );

        $this->assertSame(['id', 'role_id', 'user_invitation_id'], array_keys($ordered));
    }

    public function testDatabaseOrderKeepsTheProvidedSequence(): void
    {
        $factory = $this->makeFactory([]);

        $ordered = $factory->orderedItems(
            ['role_id' => 'int', 'user_invitation_id' => 'int', 'id' => 'int'],
            'database'
        );

        $this->assertSame(['role_id', 'user_invitation_id', 'id'], array_keys($ordered));
    }

    public function testArrayOrderAppliesTheExplicitSequence(): void
    {
        $factory = $this->makeFactory([]);

        $ordered = $factory->orderedItems(
            ['role_id' => 'int', 'user_invitation_id' => 'int', 'id' => 'int', 'extra' => 'int'],
            ['id', 'role_id']
        );

        $this->assertSame(['id', 'role_id', 'extra', 'user_invitation_id'], array_keys($ordered));
    }

    public function testPropertiesHonorDatabaseOrderFromConfig(): void
    {
        $factory = $this->makeFactory([
            '*' => ['parent' => Model::class],
            'invitation_roles' => ['annotations_order' => 'database'],
        ]);

        $model = new Model(
            $this->blueprint(),
            $factory,
            [],
            false
        );

        $annotations = $model->getProperties();
        $this->assertSame(['role_id', 'id', 'user_invitation_id'], array_keys($annotations));

        $doc = $factory->docProperties($model);

        // "database" keeps the column order (role_id before id)
        $this->assertStringContainsString('@property int $role_id', $doc);
        $this->assertLessThan(
            strpos($doc, '@property int $user_invitation_id'),
            strpos($doc, '@property int $role_id')
        );
    }

    public function testPropertiesDefaultToAlphabetical(): void
    {
        $factory = $this->makeFactory(['*' => ['parent' => Model::class]]);

        $model = new Model(
            $this->blueprint(),
            $factory,
            [],
            false
        );

        $doc = $factory->docProperties($model);

        // Default sorts alphabetically: id before role_id
        $this->assertStringContainsString('@property int $id', $doc);
        $this->assertLessThan(
            strpos($doc, '@property int $role_id'),
            strpos($doc, '@property int $id')
        );
    }

    private function blueprint(): Blueprint
    {
        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('table')->andReturn('invitation_roles');
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.invitation_roles');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('isView')->andReturn(false);
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $blueprint->shouldReceive('relations')->andReturn([]);
        $blueprint->shouldReceive('columns')->andReturn([
            // Inserted in a non-alphabetical order to assert ordering control
            new Fluent(['name' => 'role_id', 'type' => 'int', 'nullable' => false, 'comment' => null]),
            new Fluent(['name' => 'id', 'type' => 'int', 'nullable' => false, 'comment' => null]),
            new Fluent(['name' => 'user_invitation_id', 'type' => 'int', 'nullable' => false, 'comment' => null]),
        ]);

        return $blueprint;
    }
}