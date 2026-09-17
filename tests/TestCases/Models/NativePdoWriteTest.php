<?php

declare(strict_types=1);

namespace TestCases\Models;

use Dibi\Drivers\PdoDriver;
use Dibi\Expression;
use Dibi\Literal;
use Lsr\Db\DB;
use Mocks\Models\ModelWithExpression;
use PHPUnit\Framework\TestCase;

/**
 * The ORM writes scalar data with native PDO statements and falls back to dibi for everything else.
 * Only dibi's generic PDO driver enables that path, so these tests run on it exclusively.
 */
class NativePdoWriteTest extends TestCase
{
    use DbHelpers;

    public function setUp(): void {
        $this->initPdoDb();
        $this->assertInstanceOf(PdoDriver::class, DB::getConnection()->connection->getDriver());

        parent::setUp();
    }

    public function tearDown(): void {
        $this->cleanupDb();
        parent::tearDown();
    }

    public function test_insert_with_an_expression_value(): void {
        $model = new ModelWithExpression();
        $model->name = 'inserted';
        $model->value = new Expression('upper(%s)', 'expression-insert');
        $this->assertTrue($model->save());
        $this->assertNotNull($model->id);

        $this->assertSame('EXPRESSION-INSERT', $this->dbValue('value', $model->id));
        // The row must be inserted exactly once, whichever path wrote it
        $this->assertSame(1, (int) DB::select(ModelWithExpression::TABLE, 'COUNT(*)')->fetchSingle(false));
    }

    private function dbValue(string $column, ?int $id): mixed {
        return DB::select(ModelWithExpression::TABLE, $column)
            ->where('id_model = %i', $id)
            ->fetchSingle(false);
    }

    public function test_update_with_an_expression_value(): void {
        $model = new ModelWithExpression();
        $model->name = 'created';
        $this->assertTrue($model->save());

        $model->name = 'updated';
        $model->value = new Expression('upper(%s)', 'expression-update');
        $this->assertTrue($model->save());

        $this->assertSame('updated', $this->dbValue('name', $model->id));
        $this->assertSame('EXPRESSION-UPDATE', $this->dbValue('value', $model->id));
    }

    public function test_update_with_a_literal_value(): void {
        $model = new ModelWithExpression();
        $model->name = 'literal';
        $model->value = 1;
        $this->assertTrue($model->save());

        $model->value = new Literal('value + 41');
        $this->assertTrue($model->save());

        $this->assertSame(42, (int) $this->dbValue('value', $model->id));
    }

    public function test_scalar_and_null_values_round_trip(): void {
        $model = new ModelWithExpression();
        $model->name = 'scalar';
        $model->value = 42;
        $this->assertTrue($model->save());

        $this->assertSame('scalar', $this->dbValue('name', $model->id));
        $this->assertSame(42, (int) $this->dbValue('value', $model->id));

        $model->value = null;
        $this->assertTrue($model->save());
        $this->assertNull($this->dbValue('value', $model->id));

        $model->fetch(true);
        $this->assertSame('scalar', $model->name);
        $this->assertNull($model->value);
    }
}
