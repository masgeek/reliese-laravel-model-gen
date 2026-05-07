<?php

use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reliese\Coders\Model\Config;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Coders\Model\Relations\BelongsTo;
use Reliese\Coders\Model\Relations\HasMany;
use Reliese\Coders\Model\Relations\HasOne;
use Reliese\Meta\Blueprint;

class CamelCaseRelationsTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeModel(array $config = []): Model
    {
        $base = ['*' => array_merge(
            ['parent' => \Illuminate\Database\Eloquent\Model::class],
            $config
        )];

        return new Model(
            new Blueprint('test', 'test', 'test'),
            new Factory(
                \Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                \Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                \Mockery::mock(\Reliese\Support\Classify::class),
                new Config($base)
            )
        );
    }

    // -----------------------------------------------------------------------
    // Model::usesSnakeRelationNames()
    // -----------------------------------------------------------------------

    public static function provideSnakeRelationNamesCombinations(): array
    {
        // [snake_attributes, camel_case_relations, expectedUsesSnake]
        return [
            'snake attrs ON,  camel relations OFF → snake names'  => [true,  false, true],
            'snake attrs OFF, camel relations OFF → camel names'  => [false, false, false],
            'snake attrs ON,  camel relations ON  → camel names'  => [true,  true,  false],
            'snake attrs OFF, camel relations ON  → camel names'  => [false, true,  false],
        ];
    }

    #[DataProvider('provideSnakeRelationNamesCombinations')]
    public function testUsesSnakeRelationNames(
        bool $snakeAttributes,
        bool $camelRelations,
        bool $expectedUsesSnake
    ): void {
        $model = $this->makeModel([
            'snake_attributes'   => $snakeAttributes,
            'camel_case_relations' => $camelRelations,
        ]);

        $this->assertSame($expectedUsesSnake, $model->usesSnakeRelationNames());
    }

    public function testUsesSnakeRelationNamesDefaultsToSnakeAttributesWhenNotSet(): void
    {
        // camel_case_relations not set at all → falls back to snake_attributes (true by default)
        $model = $this->makeModel();

        $this->assertTrue($model->usesSnakeRelationNames());
    }

    // -----------------------------------------------------------------------
    // camel_case_relations: true produces camelCase method names while
    // snake_attributes: true keeps property names as snake_case.
    // -----------------------------------------------------------------------

    public function testHasManyNameIsCamelCaseWhenCamelCaseRelationsEnabled(): void
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn('ApiKey');

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('getRelationNameStrategy')->andReturn('related');
        // camel_case_relations: true → usesSnakeRelationNames() returns false
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn(false);

        $relationship = Mockery::mock(HasMany::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertSame('apiKeys', $relationship->name());
    }

    public function testHasManyNameIsSnakeCaseWhenCamelCaseRelationsDisabled(): void
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn('ApiKey');

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('getRelationNameStrategy')->andReturn('related');
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn(true);

        $relationship = Mockery::mock(HasMany::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertSame('api_keys', $relationship->name());
    }

    public function testHasOneNameIsCamelCaseWhenCamelCaseRelationsEnabled(): void
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn('UserProfile');

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn(false);

        $relationship = Mockery::mock(HasOne::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertSame('userProfile', $relationship->name());
    }

    public function testBelongsToNameIsCamelCaseWhenCamelCaseRelationsEnabled(): void
    {
        $relation = Mockery::mock(Fluent::class)->makePartial();

        $relatedModel = Mockery::mock(Model::class)->makePartial();
        $relatedModel->shouldReceive('getClassName')->andReturn('UserAccount');

        $subject = Mockery::mock(Model::class)->makePartial();
        $subject->shouldReceive('getRelationNameStrategy')->andReturn('related');
        $subject->shouldReceive('usesSnakeRelationNames')->andReturn(false);

        $relationship = Mockery::mock(BelongsTo::class, [$relation, $subject, $relatedModel])->makePartial();

        $this->assertSame('userAccount', $relationship->name());
    }

    // -----------------------------------------------------------------------
    // MySQL FK regex — named constraints with non-word characters
    // -----------------------------------------------------------------------

    public static function provideMySqlFkConstraintSql(): array
    {
        // Each row: [CREATE TABLE fragment, expectedConstraintName, expectedFkCols, expectedRefTable, expectedRefCols]
        return [
            'standard word constraint name' => [
                'CONSTRAINT fk_posts_user_id FOREIGN KEY (user_id) REFERENCES users (id)',
                'fk_posts_user_id', 'user_id', 'users', 'id',
            ],
            'hyphenated constraint name' => [
                'CONSTRAINT fk-posts-user-id FOREIGN KEY (user_id) REFERENCES users (id)',
                'fk-posts-user-id', 'user_id', 'users', 'id',
            ],
            'constraint with dots' => [
                'CONSTRAINT my.schema.fk_ref FOREIGN KEY (ref_id) REFERENCES refs (id)',
                'my.schema.fk_ref', 'ref_id', 'refs', 'id',
            ],
        ];
    }

    #[DataProvider('provideMySqlFkConstraintSql')]
    public function testMySqlFkRegexCapturesConstraintName(
        string $sql,
        string $expectedConstraint,
        string $expectedFkCols,
        string $expectedRefTable,
        string $expectedRefCols
    ): void {
        $pattern = '/CONSTRAINT\s+(\S+)\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(\s]+)\s*\(([^\)]+)\)/mi';
        $matches = [];

        $this->assertSame(1, preg_match($pattern, $sql, $matches),
            "Pattern did not match SQL: $sql");

        $this->assertSame($expectedConstraint, $matches[1], 'Constraint name mismatch');
        $this->assertSame($expectedFkCols,     $matches[2], 'FK columns mismatch');
        $this->assertSame($expectedRefTable,   $matches[3], 'Referenced table mismatch');
        $this->assertSame($expectedRefCols,    $matches[4], 'Referenced columns mismatch');
    }

    public function testMySqlFkRegexDoesNotMatchWithoutConstraintClause(): void
    {
        // Raw FOREIGN KEY without CONSTRAINT prefix should not match.
        $sql = 'FOREIGN KEY (user_id) REFERENCES users (id)';
        $pattern = '/CONSTRAINT\s+(\S+)\s+FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(\s]+)\s*\(([^\)]+)\)/mi';

        $this->assertSame(0, preg_match($pattern, $sql),
            'Pattern should not match SQL without CONSTRAINT clause');
    }
}
