<?php
declare(strict_types=1);

namespace TestCases\Models;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\ModelCollection;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\Models\ModelWithRelationFactories;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ModelWithRelationFactoriesTest extends TestCase
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

    public function setUp() : void {
        DB::init(
            new Connection(
                $this->cache,
                $this->mapper,
                [
                    'driver'   => "sqlite",
                    'database' => ROOT."tests/tmp/dbModels.db",
                    'prefix'   => "",
                ]
            )
        );
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsWithRelationFactories ( 
			    id INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        $this->refreshData();

        $files = glob(TMP_DIR.'models/*');
        assert($files !== false);
        foreach ($files as $file) {
            unlink($file);
        }

        parent::setUp();
    }

    public function refreshData() : void {
        DB::delete(ModelWithRelationFactories::TABLE, ['1 = 1']);
        DB::insert(
            ModelWithRelationFactories::TABLE,
            [
                'id'   => 1,
                'name' => 'model1',
            ]
        );
        DB::insert(
            ModelWithRelationFactories::TABLE,
            [
                'id'   => 2,
                'name' => 'model2',
            ]
        );

        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown() : void {
        DB::close();
        parent::tearDown();
    }

    public function testFetch() : void {
        $model = ModelWithRelationFactories::get(1);

        // Eager loaded factories are called on fetch
        $this->assertEquals(2, $model::$parentFactoryCallCount);
        $this->assertEquals(2, $model::$collectionFactoryCallCount);

        $this->assertInstanceOf(ModelWithRelationFactories::class, $model->parent);
        $this->assertEquals(999, $model->parent->id);
        $this->assertEquals('parent', $model->parent->name);
        $this->assertEquals(3, $model::$parentFactoryCallCount);

        $this->assertInstanceOf(ModelWithRelationFactories::class, $model->parent2);
        $this->assertEquals(999, $model->parent2->id);
        $this->assertEquals('parent', $model->parent2->name);
        $this->assertEquals(4, $model::$parentFactoryCallCount);

        $this->assertInstanceOf(ModelCollection::class, $model->manyToMany);
        $this->assertCount(1, $model->manyToMany);
        $this->assertArrayHasKey(9999, $model->manyToMany);
        $this->assertEquals(9999, $model->manyToMany[9999]->id);
        $this->assertEquals('collection', $model->manyToMany[9999]->name);
        $this->assertEquals(3, $model::$collectionFactoryCallCount);

        $this->assertInstanceOf(ModelCollection::class, $model->oneToMany);
        $this->assertCount(1, $model->oneToMany);
        $this->assertArrayHasKey(9999, $model->oneToMany);
        $this->assertEquals(9999, $model->oneToMany[9999]->id);
        $this->assertEquals('collection', $model->oneToMany[9999]->name);
        $this->assertEquals(4, $model::$collectionFactoryCallCount);

        $this->assertInstanceOf(ModelWithRelationFactories::class, $model->parentEager);
        $this->assertEquals(999, $model->parentEager->id);
        $this->assertEquals('parent', $model->parentEager->name);

        $this->assertInstanceOf(ModelWithRelationFactories::class, $model->parent2Eager);
        $this->assertEquals(999, $model->parent2Eager->id);
        $this->assertEquals('parent', $model->parent2Eager->name);

        $this->assertInstanceOf(ModelCollection::class, $model->manyToManyEager);
        $this->assertCount(1, $model->manyToManyEager);
        $this->assertArrayHasKey(9999, $model->manyToManyEager);
        $this->assertEquals(9999, $model->manyToManyEager[9999]->id);
        $this->assertEquals('collection', $model->manyToManyEager[9999]->name);

        $this->assertInstanceOf(ModelCollection::class, $model->oneToManyEager);
        $this->assertCount(1, $model->oneToManyEager);
        $this->assertArrayHasKey(9999, $model->oneToManyEager);
        $this->assertEquals(9999, $model->oneToManyEager[9999]->id);
        $this->assertEquals('collection', $model->oneToManyEager[9999]->name);

        // Should not increase when accessing eager loaded relations
        $this->assertEquals(4, $model::$parentFactoryCallCount);
        $this->assertEquals(4, $model::$collectionFactoryCallCount);
    }
}