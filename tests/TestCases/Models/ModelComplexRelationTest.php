<?php

namespace TestCases\Models;

use Dibi\DriverException;
use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Mocks\Models\ModelCA;
use Mocks\Models\ModelCB;
use Mocks\Models\ModelCC;

class ModelComplexRelationTest extends TestCase
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

    public function setUp(): void {
        DB::init(
            new Connection(
                $this->cache,
                $this->mapper,
                [
                    'database' => ROOT . "tests/tmp/dbModelsComplex.db",
                    'driver'   => "sqlite",
                    'prefix'   => "",
                ]
            )
        );
        try {
            DB::getConnection()->query(
                "
                CREATE TABLE models_a ( 
                    id_model_a INTEGER PRIMARY KEY autoincrement NOT NULL, 
                    name CHAR(60) NOT NULL, 
                    id_model_b INT NOT NULL,
                    id_model_c INT NOT NULL 
                );
            "
            );
        } catch (DriverException) {
        }
        try {
            DB::getConnection()->query(
                "
                CREATE TABLE models_b ( 
                    id_model_b INTEGER PRIMARY KEY autoincrement NOT NULL, 
                    name CHAR(60) NOT NULL
                );
            "
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
                CREATE TABLE models_c ( 
                    id_model_c INTEGER PRIMARY KEY autoincrement NOT NULL,
                    name CHAR(60) NOT NULL,
                    id_model_b INT NOT NULL
                );
            "
            );
        } catch (Exception) {
        }

        $this->refreshData();

        $files = glob(TMP_DIR . 'models/*');
        assert($files !== false);
        foreach ($files as $file) {
            unlink($file);
        }

        parent::setUp();
    }

    private function refreshData(): void {
        DB::delete(ModelCA::TABLE, ['1 = 1']);
        DB::delete(ModelCB::TABLE, ['1 = 1']);
        DB::delete(ModelCC::TABLE, ['1 = 1']);

        DB::insert(
            ModelCB::TABLE,
            [
            'id_model_b' => 1,
            'name'       => 'Parent1',
            ]
        );
        DB::insert(
            ModelCB::TABLE,
            [
            'id_model_b' => 2,
            'name'       => 'Parent2',
            ]
        );

        DB::insert(
            ModelCC::TABLE,
            [
            'id_model_c' => 1,
            'id_model_b' => 1,
            'name'       => 'Group1',
            ]
        );
        DB::insert(
            ModelCC::TABLE,
            [
            'id_model_c' => 2,
            'id_model_b' => 1,
            'name'       => 'Group2',
            ]
        );
        DB::insert(
            ModelCC::TABLE,
            [
            'id_model_c' => 3,
            'id_model_b' => 1,
            'name'       => 'Group3',
            ]
        );

        DB::insert(
            ModelCC::TABLE,
            [
            'id_model_c' => 4,
            'id_model_b' => 2,
            'name'       => 'Group4',
            ]
        );
        DB::insert(
            ModelCC::TABLE,
            [
            'id_model_c' => 5,
            'id_model_b' => 2,
            'name'       => 'Group5',
            ]
        );

        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 1,
            'name'       => 'Model1',
            'id_model_b' => 1,
            'id_model_c' => 1,
            ]
        );
        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 2,
            'name'       => 'Model2',
            'id_model_b' => 1,
            'id_model_c' => 1,
            ]
        );
        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 3,
            'name'       => 'Model3',
            'id_model_b' => 1,
            'id_model_c' => 1,
            ]
        );

        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 4,
            'name'       => 'Model4',
            'id_model_b' => 1,
            'id_model_c' => 2,
            ]
        );
        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 5,
            'name'       => 'Model5',
            'id_model_b' => 1,
            'id_model_c' => 2,
            ]
        );

        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 6,
            'name'       => 'Model6',
            'id_model_b' => 1,
            'id_model_c' => 3,
            ]
        );

        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 7,
            'name'       => 'Model7',
            'id_model_b' => 2,
            'id_model_c' => 4,
            ]
        );

        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 8,
            'name'       => 'Model8',
            'id_model_b' => 2,
            'id_model_c' => 5,
            ]
        );
        DB::insert(
            ModelCA::TABLE,
            [
            'id_model_a' => 9,
            'name'       => 'Model9',
            'id_model_b' => 2,
            'id_model_c' => 6,
            ]
        );

        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown(): void {
        DB::close();
        $this->cache->clean([Cache::All => true]);
        parent::tearDown();
    }

    public function testModelA(): void {
        $model = ModelCA::get(1);

        self::assertEquals('Model1', $model->name);
        self::assertEquals(1, $model->parent->id);
        self::assertEquals('Parent1', $model->parent->name);

        self::assertTrue(isset($model->parentC));
        self::assertTrue(isset($model->parentC));
        self::assertEquals(1, $model->parentC->id);
        self::assertEquals('Group1', $model->parentC->name);
    }

    public function testModelB(): void {
        $model = ModelCB::get(1);

        self::assertEquals('Parent1', $model->name);

        self::assertCount(6, $model->children);
        self::assertCount(3, $model->childrenC);

        self::assertContains(ModelCA::get(1), $model->children);
        self::assertContains(ModelCA::get(2), $model->children);
        self::assertContains(ModelCA::get(3), $model->children);
        self::assertContains(ModelCA::get(4), $model->children);
        self::assertContains(ModelCA::get(5), $model->children);
        self::assertContains(ModelCA::get(6), $model->children);

        self::assertContains(ModelCC::get(1), $model->childrenC);
        self::assertContains(ModelCC::get(2), $model->childrenC);
        self::assertContains(ModelCC::get(3), $model->childrenC);

        $model = ModelCB::get(2);

        self::assertEquals('Parent2', $model->name);

        self::assertCount(3, $model->children);
        self::assertCount(2, $model->childrenC);

        self::assertContains(ModelCA::get(7), $model->children);
        self::assertContains(ModelCA::get(8), $model->children);
        self::assertContains(ModelCA::get(9), $model->children);

        self::assertContains(ModelCC::get(4), $model->childrenC);
        self::assertContains(ModelCC::get(5), $model->childrenC);
    }

    public function testModelC(): void {
        $model = ModelCC::get(1);

        self::assertEquals('Group1', $model->name);

        self::assertTrue(isset($model->children));
        self::assertCount(3, $model->children);

        self::assertCount(3, $model->children);

        self::assertContains(ModelCA::get(1), $model->children);
        self::assertContains(ModelCA::get(2), $model->children);
        self::assertContains(ModelCA::get(3), $model->children);
    }
}
