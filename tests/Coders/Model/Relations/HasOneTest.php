<?php

use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relations\HasOne;

class HasOneTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
    }

    public static function provideRelatedStrategyPermutations(): array
    {
        // [usesSnakeRelationNames, relatedClassName, expected]
        return [
            'snake relation names'  => [true,  'LineManager', 'line_manager'],
            'camel relation names'  => [false, 'LineManager', 'lineManager'],
            'single-word camel'     => [false, 'User',        'user'],
            'single-word snake'     => [true,  'User',        'user'],
        ];
    }

    #[DataProvider('provideRelatedStrategyPermutations')]
    public function testName(bool $usesSnakeRelationNames, string $relatedClassName, string $expected): void
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn($relatedClassName);

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn($usesSnakeRelationNames);

        /** @var HasOne|\Mockery\Mock $relationship */
        $relationship = Mockery::mock(HasOne::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertEquals(
            $expected,
            $relationship->name(),
            json_encode(compact('usesSnakeRelationNames', 'relatedClassName'))
        );
    }
}
