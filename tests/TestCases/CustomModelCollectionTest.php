<?php
declare(strict_types=1);

namespace TestCases;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\CustomCollection;
use Mocks\CustomInvalidCollection;
use Mocks\Models\ModelF;
use Mocks\Models\ModelFInvalid;
use Mocks\Models\ModelFInvalid2;
use Mocks\Models\ModelG;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CustomModelCollectionTest extends TestCase
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
			CREATE TABLE modelsF ( 
			    model_f_id INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsF_connect ( 
			    id_1 INTEGER, 
			    id_2 INTEGER, 
			    PRIMARY KEY(id_1, id_2)
			);
		"
            );
        } catch (Exception) {
        }
        try {
            DB::getConnection()->query(
                "
			CREATE TABLE modelsG ( 
			    model_g_id INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    name CHAR(200) NOT NULL, 
			    model_f_id INT NOT NULL
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
        DB::delete(ModelF::TABLE, ['1 = 1']);
        DB::delete(ModelG::TABLE, ['1 = 1']);
        DB::delete('modelsF_connect', ['1 = 1']);

        DB::insert(
            ModelF::TABLE,
            [
                'model_f_id' => 1,
                'name'       => 'model1',
            ]
        );
        DB::insert(
            ModelF::TABLE,
            [
                'model_f_id' => 2,
                'name'       => 'model2',
            ]
        );

        DB::insert(
            'modelsF_connect',
            [
                'id_1' => 1,
                'id_2' => 2,
            ]
        );
        DB::insert(
            'modelsF_connect',
            [
                'id_1' => 2,
                'id_2' => 1,
            ]
        );

        DB::insert(
            ModelG::TABLE,
            [
                'model_g_id' => 1,
                'name'       => 'model1',
                'model_f_id' => 1,
            ]
        );
        DB::insert(
            ModelG::TABLE,
            [
                'model_g_id' => 2,
                'name'       => 'model2',
                'model_f_id' => 1,
            ]
        );
        DB::insert(
            ModelG::TABLE,
            [
                'model_g_id' => 3,
                'name'       => 'model3',
                'model_f_id' => 1,
            ]
        );
        DB::insert(
            ModelG::TABLE,
            [
                'model_g_id' => 4,
                'name'       => 'model4',
                'model_f_id' => 2,
            ]
        );
        DB::insert(
            ModelG::TABLE,
            [
                'model_g_id' => 5,
                'name'       => 'model5',
                'model_f_id' => 2,
            ]
        );

        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown() : void {
        DB::close();
        parent::tearDown();
    }

    public function testInitCustomCollection() : void {
        $model = new ModelF();

        // Should be initialized
        $this->assertTrue(isset($model->models));
        $this->assertInstanceOf(CustomCollection::class, $model->models);

        $this->assertTrue(isset($model->manyToMany));
        $this->assertInstanceOf(CustomCollection::class, $model->manyToMany);
    }

    public function testCollectionFetch() : void {
        $model = ModelF::get(1);

        // Should be initialized
        $this->assertTrue(isset($model->models));
        $this->assertInstanceOf(CustomCollection::class, $model->models);
        $this->assertTrue(isset($model->manyToMany));
        $this->assertInstanceOf(CustomCollection::class, $model->manyToMany);

        // Should contain other models
        $this->assertContains(ModelG::get(1), $model->models);
        $this->assertContains(ModelG::get(2), $model->models);
        $this->assertContains(ModelG::get(3), $model->models);
        $this->assertContains(ModelF::get(2), $model->manyToMany);
    }

    public function testInvalidCollectionFetch() : void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid property type %s for relation type %s on %s::$%s (must extend %s)',
                CustomInvalidCollection::class,
                OneToMany::class,
                ModelFInvalid::class,
                'models',
                ModelCollection::class,
            )
        );
        $model = ModelFInvalid::get(1);
    }

    public function testInvalidCollectionFetch2() : void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid property type %s for relation type %s on %s::$%s (must extend %s)',
                CustomInvalidCollection::class,
                ManyToMany::class,
                ModelFInvalid2::class,
                'manyToMany',
                ModelCollection::class,
            )
        );
        $model = ModelFInvalid2::get(1);
    }
}