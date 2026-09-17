<?php

declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Db\DB;
use Mocks\Models\ModelWithColumnTypes;
use Mocks\Types\JsonType;
use Mocks\Types\UpperCaseType;
use PHPUnit\Framework\TestCase;

class ColumnTypeTest extends TestCase
{
    use DbHelpers;

    public function setUp(): void {
        $this->initDb();

        parent::setUp();
    }

    public function tearDown(): void {
        $this->cleanupDb();
        parent::tearDown();
    }

    public function test_column_type_converts_values_on_insert_and_load(): void {
        $model = new ModelWithColumnTypes();
        $model->tags = ['alpha', 'beta'];
        $model->settings = ['theme' => 'dark', 'rows' => 10];
        $this->assertTrue($model->save());

        // Array properties are stored through the type instead of being skipped
        $this->assertSame('["alpha","beta"]', $this->dbValue('tags', $model->id));
        $this->assertSame('{"theme":"dark","rows":10}', $this->dbValue('settings', $model->id));

        $model->fetch(true);
        $this->assertSame(['alpha', 'beta'], $model->tags);
        $this->assertSame(['theme' => 'dark', 'rows' => 10], $model->settings);
    }

    private function dbValue(string $column, ?int $id): mixed {
        return DB::select(ModelWithColumnTypes::TABLE, $column)
            ->where('id_model = %i', $id)
            ->fetchSingle(false);
    }

    public function test_column_type_converts_values_on_update(): void {
        $model = new ModelWithColumnTypes();
        $model->tags = ['alpha'];
        $model->settings = ['theme' => 'dark'];
        $this->assertTrue($model->save());

        $model->tags = ['gamma', 'delta'];
        $model->settings = null;
        $this->assertTrue($model->save());

        $this->assertSame('["gamma","delta"]', $this->dbValue('tags', $model->id));
        $this->assertNull($this->dbValue('settings', $model->id));

        $model->fetch(true);
        $this->assertSame(['gamma', 'delta'], $model->tags);
        $this->assertNull($model->settings);
    }

    public function test_column_type_expression_is_evaluated_by_the_database(): void {
        $model = new ModelWithColumnTypes();
        $model->code = 'abc-42';
        $this->assertTrue($model->save());

        $this->assertSame('ABC-42', $this->dbValue('code', $model->id));

        $model->fetch(true);
        $this->assertSame('ABC-42', $model->code);

        $model->code = null;
        $this->assertTrue($model->save());
        $this->assertNull($this->dbValue('code', $model->id));

        $model->fetch(true);
        $this->assertNull($model->code);
    }

    public function test_column_type_is_resolved_from_the_property(): void {
        $this->assertInstanceOf(JsonType::class, ModelWithColumnTypes::getColumnType('tags'));
        $this->assertInstanceOf(UpperCaseType::class, ModelWithColumnTypes::getColumnType('code'));
        $this->assertNull(ModelWithColumnTypes::getColumnType('id'));
    }
}
