<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace TestCases\Models;

use Lsr\Orm\Model;
use Lsr\Orm\ModelQuery;
use Mocks\Models\QueryModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Model queries
 *
 * @author  Tomáš Vojík
 */
#[UsesClass(Model::class)]
#[CoversClass(ModelQuery::class)]
class ModelQueryTest extends TestCase
{
    use DbHelpers;

    public function setUp(): void {
        $this->initDb('dbQuery');

        parent::setUp();
    }

    public function tearDown(): void {
        $this->cleanupDb();
        parent::tearDown();
    }

    public function testOffset(): void {
        $query = QueryModel::query()->offset(1);
        self::assertCount(3, $query->get());
    }

    public function testGet(): void {
        $query = QueryModel::query();
        $models = $query->get();
        self::assertCount(4, $models);
        foreach ($models as $model) {
            self::assertInstanceOf(QueryModel::class, $model);
            self::assertNotNull($model->id);
            self::assertSame($models[$model->id], $model);
        }
    }

    public function testOrderBy(): void {
        $query = QueryModel::query()->orderBy('age');
        $models = array_values($query->get());
        self::assertEquals(null, $models[0]->age);
        self::assertEquals(10, $models[1]->age);
        self::assertEquals(20, $models[2]->age);
        self::assertEquals(99, $models[3]->age);
    }

    public function testAsc(): void {
        $query = QueryModel::query()->orderBy('age')->asc();
        $models = array_values($query->get());
        self::assertEquals(null, $models[0]->age);
        self::assertEquals(10, $models[1]->age);
        self::assertEquals(20, $models[2]->age);
        self::assertEquals(99, $models[3]->age);

    }

    public function testJoin(): void {
        /** @var QueryModel[] $models */
        $models = QueryModel::query()
                              ->join('data', 'b')
                              ->on('a.id_model = b.id_model')
                              ->where('b.model_type = %s', 'C')
                              ->get(false);

        self::assertCount(1, $models);
        self::assertEquals(3, first($models)->id);

    }

    public function testDesc(): void {
        $query = QueryModel::query()->orderBy('age')->desc();
        $models = array_values($query->get());
        self::assertEquals(null, $models[3]->age);
        self::assertEquals(10, $models[2]->age);
        self::assertEquals(20, $models[1]->age);
        self::assertEquals(99, $models[0]->age);

    }

    public function testWhere(): void {
        $query = QueryModel::query()->where('age >= 20');
        $models = $query->get(false);
        self::assertCount(2, $models);
        self::assertEquals([1, 3], array_keys($models));

    }

    public function testCount(): void {
        $count = QueryModel::query()->count();
        self::assertEquals(4, $count);
    }

    public function testLimit(): void {
        $models = QueryModel::query()->limit(2)->get();
        self::assertCount(2, $models);

    }

    public function testFirst(): void {
        $model = QueryModel::query()->first();
        self::assertNotNull($model);
        self::assertInstanceOf(QueryModel::class, $model);
        self::assertEquals(1, $model->id);
    }

    public function testFirstEmpty(): void {
        $model = QueryModel::query()->where('1 = 0')->first();
        self::assertNull($model);
    }
}
