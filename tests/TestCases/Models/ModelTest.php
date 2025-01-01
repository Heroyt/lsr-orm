<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace TestCases\Models;

use Dibi\Exception;
use Dibi\Row;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\Models\ModelA;
use Mocks\Models\ModelB;
use Mocks\Models\ModelBLazy;
use Mocks\Models\ModelC;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use Mocks\Models\ModelInvalid;
use Mocks\Models\ModelInvalidInstantiate;
use Mocks\Models\ModelInvalidInstantiate2;
use Mocks\Models\ModelPk1;
use Mocks\Models\ModelPk2;
use Mocks\Models\ModelWithTimestamps;
use Mocks\Models\SimpleData;
use Mocks\Models\TestEnum;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use function json_encode;


/**
 * Test suite for models
 *
 * @author Tomáš Vojík
 */
class ModelTest extends TestCase
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
                    'driver'   => "sqlite",
                    'database' => ROOT . "tests/tmp/dbModels.db",
                    'prefix'   => "",
                ]
            )
        );
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsA ( 
			    model_a_id INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL, 
			    age INT,
			    verified INT DEFAULT 0 
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsB ( 
			    model_b_id INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    description CHAR(200) NOT NULL, 
			    model_type CHAR(1) NOT NULL, 
			    model_a_id INT 
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsC ( 
			    model_c_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    value0 CHAR(50) NOT NULL, 
			    value1 CHAR(50) NOT NULL, 
			    value2 CHAR(50) NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsD ( 
			    model_d_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(50) NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsE ( 
			    model_e_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(50) NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsD_modelsE ( 
			    model_d_id INTEGER NOT NULL,
			    model_e_id INTEGER NOT NULL,
			  	PRIMARY KEY(model_d_id, model_e_id)
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE model_invalid_instantiate ( 
			    id_model INTEGER PRIMARY KEY autoincrement NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE with_timestamps ( 
			    id_with_timestamps INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(50) NOT NULL,
			    updated_at TIMESTAMP DEFAULT NULL,
			    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

    public function refreshData(): void {
        DB::delete(ModelA::TABLE, ['1 = 1']);
        DB::delete(ModelB::TABLE, ['1 = 1']);
        DB::delete(ModelC::TABLE, ['1 = 1']);
        DB::delete(ModelD::TABLE, ['1 = 1']);
        DB::delete(ModelE::TABLE, ['1 = 1']);
        DB::delete(ModelWithTimestamps::TABLE, ['1 = 1']);
        DB::delete(ModelInvalidInstantiate::TABLE, ['1 = 1']);
        DB::delete('modelsD_modelsE', ['1 = 1']);

        DB::insert(
            ModelA::TABLE,
            [
                'model_a_id' => 1,
                'name'       => 'model1',
                'age'        => 20,
                'verified' => false,
            ]
        );

        DB::insert(
            ModelA::TABLE,
            [
                'model_a_id' => 2,
                'name'       => 'model2',
                'age'        => null,
                'verified' => true,
            ]
        );

        DB::insert(
            ModelB::TABLE,
            [
                'model_b_id'  => 1,
                'description' => 'Lorem ipsum',
                'model_type'  => 'A',
                'model_a_id'  => 1,
            ]
        );
        DB::insert(
            ModelB::TABLE,
            [
                'model_b_id'  => 2,
                'description' => 'Lorem ipsumaaaaa',
                'model_type'  => 'A',
                'model_a_id'  => 1,
            ]
        );
        DB::insert(
            ModelB::TABLE,
            [
                'model_b_id'  => 3,
                'description' => 'Lorem ipsumbbbbbb',
                'model_type'  => 'C',
                'model_a_id'  => 2,
            ]
        );
        DB::insert(
            ModelB::TABLE,
            [
                'model_b_id'  => 4,
                'description' => 'Lorem dasmdlsakdnad',
                'model_type'  => 'D',
                'model_a_id'  => null,
            ]
        );
        DB::insert(
            ModelC::TABLE,
            [
                'model_c_id' => 1,
                'value0'     => 'value0',
                'value1'     => 'value1',
                'value2'     => 'value2',
            ]
        );
        DB::insert(
            ModelC::TABLE,
            [
                'model_c_id' => 2,
                'value0'     => 'a',
                'value1'     => 'b',
                'value2'     => 'c',
            ]
        );

        DB::insert(
            ModelE::TABLE,
            [
                'model_e_id' => 1,
                'name'       => 'a',
            ]
        );
        DB::insert(
            ModelE::TABLE,
            [
                'model_e_id' => 2,
                'name'       => 'b',
            ]
        );
        DB::insert(
            ModelE::TABLE,
            [
                'model_e_id' => 3,
                'name'       => 'c',
            ]
        );

        DB::insert(
            ModelD::TABLE,
            [
                'model_d_id' => 1,
                'name'       => 'a',
            ]
        );
        DB::insert(
            ModelD::TABLE,
            [
                'model_d_id' => 2,
                'name'       => 'b',
            ]
        );
        DB::insert(
            ModelD::TABLE,
            [
                'model_d_id' => 3,
                'name'       => 'c',
            ]
        );
        DB::insert(
            ModelInvalidInstantiate::TABLE,
            [
                'id_model' => 1,
            ]
        );

        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 1,
                'model_e_id' => 1,
            ]
        );
        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 1,
                'model_e_id' => 2,
            ]
        );
        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 1,
                'model_e_id' => 3,
            ]
        );
        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 2,
                'model_e_id' => 1,
            ]
        );
        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 2,
                'model_e_id' => 3,
            ]
        );
        DB::insert(
            'modelsD_modelsE',
            [
                'model_d_id' => 3,
                'model_e_id' => 1,
            ]
        );
        DB::insert(
            ModelWithTimestamps::TABLE,
            [
                'id_with_timestamps' => 1,
                'name' => 'test timestamp',
            ]
        );
        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown(): void {
        DB::close();
        parent::tearDown();
    }

    public function testConstruct(): void {
        $this->refreshData();

        // Test row only
        /** @var Row|null $row */
        $row = DB::select(ModelA::TABLE, '*')->where('%n = %i', ModelA::getPrimaryKey(), 1)->fetch();
        $model = new ModelA(dbRow: $row);
        self::assertEquals(1, $model->id);
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);

        // Test row only without ID
        unset($row->model_a_id);
        $model = new ModelA(dbRow: $row);
        self::assertEquals(null, $model->id);
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);
    }

    public function testFetch(): void {
        $this->refreshData();
        $model = new ModelA();
        $model->id = 1;
        $model->fetch();
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);
    }

    public function testInvalidFetch(): void {
        $model = new ModelA();

        $this->expectException(RuntimeException::class);
        $model->fetch();
    }

    public function testGet(): void {
        $this->refreshData();
        $model1 = ModelA::get(1);

        self::assertEquals('model1', $model1->name);
        self::assertEquals(20, $model1->age);

        // Check if DB is correctly initialized
        self::assertEquals(2, DB::select(ModelB::TABLE, '*')->where('model_a_id = %i', 1)->count(cache: false));
        self::assertCount(2, $model1->children);

        $model2 = ModelB::get(1);

        self::assertEquals('Lorem ipsum', $model2->description);
        self::assertEquals(TestEnum::A, $model2->modelType);
        self::assertSame($model1, $model2->parent);

        $model3 = ModelB::get(4);

        self::assertEquals('Lorem dasmdlsakdnad', $model3->description);
        self::assertEquals(TestEnum::D, $model3->modelType);
        self::assertNull($model3->parent);

        $model4 = ModelC::get(1);

        self::assertEquals('value0', $model4->value0);
        self::assertEquals('value1', $model4->data->value1);
        self::assertEquals('value2', $model4->data->value2);
    }

    public function testRelations(): void {
        $parent = ModelA::get(1);

        $modelEager = ModelB::get(1);
        $modelLazy = ModelBLazy::get(1);

        self::assertTrue(isset($modelEager->parent));
        self::assertTrue(isset($modelLazy->parent));


        self::assertEquals($parent, $modelEager->parent);
        self::assertEquals($parent->id, $modelLazy->parent->id);
        self::assertEquals($parent->name, $modelLazy->parent->name);
    }

    public function testRelationsSave(): void {
        $parent1 = ModelA::get(1);
        $parent2 = ModelA::get(2);

        $modelEager = ModelB::get(1);
        $modelLazy = ModelBLazy::get(2);

        self::assertTrue(isset($modelEager->parent));
        self::assertTrue(isset($modelLazy->parent));

        // Eager model should update its relation normally
        $modelEager->parent = $parent2;
        $data = $modelEager->getQueryData();
        self::assertEquals(2, $data['model_a_id']);
        self::assertArrayHasKey('model_a_id', $data);
        self::assertTrue($modelEager->save());
        $testId = DB::select(ModelB::TABLE, 'model_a_id')->where('model_b_id = %i', 1)->fetchSingle(cache: false);
        self::assertEquals(2, $testId);

        // Save without setting any parent (parent parameter is not set) should not change its value
        $data = $modelLazy->getQueryData();
        // @phpstan-ignore argument.type
        self::assertArrayHasKey('model_a_id', $data, json_encode($data));
        self::assertTrue($modelLazy->save());
        $testId = DB::select(ModelBLazy::TABLE, 'model_a_id')->where('model_b_id = %i', 2)->fetchSingle(cache: false);
        self::assertEquals(1, $testId);

        // After setting the value, it should behave as expected
        $modelLazy->parent = $parent2;
        $data = $modelLazy->getQueryData();
        self::assertArrayHasKey('model_a_id', $data);
        self::assertEquals(2, $data['model_a_id']);
        self::assertTrue($modelLazy->save());
        $testId = DB::select(ModelBLazy::TABLE, 'model_a_id')->where('model_b_id = %i', 2)->fetchSingle(cache: false);
        self::assertEquals(2, $testId);
    }

    public function testGetAll(): void {
        $this->refreshData();

        $models = ModelA::getAll();

        self::assertCount(2, $models);
        self::assertTrue(isset($models[1]));
        self::assertInstanceOf(ModelA::class, $models[1]);
        self::assertEquals(1, $models[1]->id);
        self::assertEquals('model1', $models[1]->name);
        self::assertEquals(20, $models[1]->age);
        self::assertCount(2, $models[1]->children);

        self::assertTrue(isset($models[2]));
        self::assertInstanceOf(ModelA::class, $models[2]);
        self::assertEquals(2, $models[2]->id);
        self::assertEquals('model2', $models[2]->name);
        self::assertEquals(null, $models[2]->age);
        self::assertCount(1, $models[2]->children);
    }

    public function testRepetitiveGet(): void {
        $this->refreshData();
        $model1 = ModelA::get(1);
        $model2 = ModelA::get(1);

        self::assertSame($model1, $model2);
    }

    public function testGetQueryData(): void {
        $model = new ModelA();
        $model->name = 'test';
        $model->age = 10;

        self::assertEquals(['name' => 'test', 'age' => 10, 'verified' => false], $model->getQueryData());

        $model->id = 99;

        $model2 = new ModelB();
        $model2->description = 'abcd';
        $model2->parent = $model;
        $model2->modelType = TestEnum::C;
        self::assertEquals(
            ['description' => 'abcd', 'model_a_id' => 99, 'model_type' => TestEnum::C->value],
            $model2->getQueryData()
        );

        $model3 = new ModelC();
        $model3->value0 = 'a';
        $model3->data = new SimpleData(
            'b',
            'c'
        );
        self::assertEquals(['value0' => 'a', 'value1' => 'b', 'value2' => 'c'], $model3->getQueryData());
    }

    public function testSave(): void {
        $model = new ModelA();
        $model->name = 'test';
        $model->age = 10;

        self::assertTrue($model->save());

        // Insert successful
        self::assertNotNull($model->id);

        // Check object caching
        self::assertSame($model, ModelA::get($model->id));

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch();
        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);

        // Update
        $model->age = 21;

        self::assertTrue($model->save());

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch(cache: false);

        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);
    }

    public function testInsertInvalid(): void {
        $model = new ModelInvalid();
        $model->column1 = 'asda';
        $model->column2 = 'asda';

        self::assertFalse($model->save());
    }

    public function testUpdateInvalid(): void {
        $model = new ModelInvalid();
        $model->column1 = 'asda';
        $model->column2 = 'asda';

        self::assertFalse($model->update());
        $model->id = 1;
        self::assertFalse($model->update());
    }

    public function testUpdate(): void {
        $model = ModelA::get(1);

        $model->name = 'testUpdate';

        self::assertTrue($model->save());

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch();
        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);
    }

    public function testArrayAccess(): void {
        $model = ModelA::get(1);

        // Test get
        self::assertEquals($model->name, $model['name']);
        self::assertEquals($model->age, $model['age']);
        self::assertNull($model['adsd']);

        // Test set
        $model['name'] = 'test set';
        self::assertEquals($model->name, $model['name']);

        // Test isset
        self::assertTrue(isset($model['name']));
        self::assertFalse(isset($model['asdas']));
    }

    public function testJsonSerialize(): void {
        $model = ModelA::get(1);
        $expected = [
            'id'       => $model->id,
            'name'     => $model->name,
            'age'      => $model->age,
            'verified' => $model->verified,
            'children' => $model->children,
        ];

        // Test data
        self::assertEquals($expected, $model->jsonSerialize());

        // Prevent recursion
        $model->children->models = [];
        $expected['children'] = [];

        // Test encoded
        self::assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), json_encode($model, JSON_THROW_ON_ERROR));
    }

    public function testPrimaryKeyGetting(): void {
        self::assertEquals('model_a_id', ModelA::getPrimaryKey());
        self::assertEquals('model_b_id', ModelB::getPrimaryKey());
        self::assertEquals('id', ModelInvalid::getPrimaryKey());
        self::assertEquals('id_model_pk1', ModelPk1::getPrimaryKey());
        self::assertEquals('model_pk2_id', ModelPk2::getPrimaryKey());
    }

    public function testExists(): void {
        $this->refreshData();
        self::assertTrue(ModelA::exists(1));
        self::assertTrue(ModelA::exists(2));
        self::assertFalse(ModelA::exists(3));
        self::assertFalse(ModelA::exists(4));
    }

    public function testDelete(): void {
        $this->refreshData();
        $model = ModelA::get(1);

        self::assertTrue($model->delete());

        self::assertNull(
            DB::select(ModelA::TABLE, '*')->where('%n = %i', ModelA::getPrimaryKey(), 1)->fetch(cache: false)
        );
        unset($model);

        $this->expectException(ModelNotFoundException::class);
        ModelA::get(1);
    }

    public function testDelete2(): void {
        $model = new ModelInvalid();

        self::assertFalse($model->delete());

        $model->id = 10;

        self::assertFalse($model->delete());
    }

    public function testManyToMany(): void {
        $model = ModelD::get(1);

        self::assertEquals('a', $model->name);
        self::assertCount(3, $model->models);

        $model2 = ModelE::get(2);
        self::assertEquals('b', $model2->name);
        self::assertCount(1, $model2->models);

        self::assertSame($model2, $model->models[2]);
        self::assertSame($model, $model2->models[1]);
    }

    public function testInvalidInstantiate(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot initialize property ' . ModelInvalidInstantiate::class . '::val with no type.'
        );
        ModelInvalidInstantiate::get(1);
    }

    public function testInvalidInstantiate2(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot initialize property ' . ModelInvalidInstantiate2::class . '::val with type int.'
        );
        ModelInvalidInstantiate2::get(1);
    }

    #[Depends('testGet'), Depends('testUpdate'), Depends('testSave')]
    public function testTimestamps(): void {
        $this->refreshData();

        $model = ModelWithTimestamps::get(1);
        $this->assertTrue(isset($model->createdAt));
        $this->assertNull($model->updatedAt);

        // New model
        $model = new ModelWithTimestamps();
        $model->name = 'new with timestamps';

        $this->assertFalse(isset($model->createdAt));
        $this->assertNull($model->updatedAt);

        // Insert
        $this->assertTrue($model->save());
        $this->assertTrue(isset($model->createdAt));
        $createdAt = $model->createdAt;
        $this->assertNull($model->updatedAt);
        $this->assertTrue(isset($model->id));

        // Update
        $model->name = 'new with timestamps - updated';
        $this->assertTrue($model->save());
        $this->assertTrue(isset($model->createdAt));
        $this->assertNotNull($model->updatedAt);
        $this->assertEquals($createdAt->format('c'), $model->createdAt->format('c'));
    }
}
