<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace TestCases\Models;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\Model;
use Lsr\Orm\ModelQuery;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Mocks\Models\QueryModel;
use Mocks\Models\DataModel;

/**
 * Test suite for Model queries
 *
 * @author  Tomáš Vojík
 */
#[UsesClass(Model::class)]
#[CoversClass(ModelQuery::class)]
class ModelQueryTest extends TestCase
{
    private Cache $cache {
        get => new Cache(new DevNullStorage());
    }

    private Mapper $mapper {
        get => new Mapper(
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new DateTimeNormalizer(),
                    new DibiRowNormalizer(),
                    new BackedEnumNormalizer(),
                    new JsonSerializableNormalizer(),
                    new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor(),),
                ]
            )
        );
    }

    /**
     * @return void
     */
    public function setUp(): void {
        DB::init(
            new Connection(
                $this->cache,
                $this->mapper,
                [
                    'database' => ROOT . "tests/tmp/dbQuery.db",
                    'driver'   => "sqlite",
                    'prefix'   => "",
                ]
            )
        );
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE models ( 
			    id_model INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    name CHAR(60) NOT NULL, 
			    age INT 
			);
		"
            );
        } catch (Exception) {

        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE data ( 
			    id INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    id_model INTEGER,
			    description CHAR(200) NOT NULL, 
			    model_type CHAR(1) NOT NULL
			);
		"
            );
        } catch (Exception) {

        }
        $this->refreshData();
        parent::setUp();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function refreshData(): void {
        DB::delete(QueryModel::TABLE, ['1 = 1']);
        DB::delete('data', ['1 = 1']);

        DB::resetAutoIncrement(QueryModel::TABLE);
        DB::resetAutoIncrement('data');

        DB::insert(
            QueryModel::TABLE,
            [
            'name' => 'model1',
            'age'  => 20,
            ],
            [
            'name' => 'model2',
            'age'  => 10,
            ],
            [
            'name' => 'model3',
            'age'  => 99,
            ],
            [
            'name' => 'model4',
            'age'  => null,
            ],
        );
        DB::insert(
            'data',
            [
            'id_model'    => 1,
            'description' => 'aasda',
            'model_type'  => 'A',
            ],
            [
            'id_model'    => 2,
            'description' => 'ahoj',
            'model_type'  => 'B',
            ],
            [
            'id_model'    => 1,
            'description' => 'desc',
            'model_type'  => 'C',
            ],
        );
    }

    public function tearDown(): void {
        DB::close();
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
        $models = QueryModel::query()
                              ->join('data', 'b')
                              ->on('a.id_model = b.id_model')
                              ->where('b.model_type = %s', 'C')
                              ->get(false);

        self::assertCount(1, $models, json_encode($models));
        /** @phpstan-ignore-next-line */
        self::assertEquals(1, first($models)->id);

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
