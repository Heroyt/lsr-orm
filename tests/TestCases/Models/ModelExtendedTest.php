<?php

declare(strict_types=1);

namespace TestCases\Models;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\ModelRepository;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\Models\ModelA;
use Mocks\Models\ModelB;
use Mocks\Models\ModelC;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use Mocks\Models\ModelInvalidInstantiate;
use Mocks\Models\ModelWithTimestamps;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Extended test suite for models covering additional features
 *
 * @author Github Copilot
 */
class ModelExtendedTest extends TestCase
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
                    'database' => ROOT."tests/tmp/dbModelsExtended.db",
                    'prefix'   => "",
                ]
            )
        );

        // Create necessary database tables
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
        } catch (\Dibi\Exception) {
            // Table might already exist
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
        } catch (\Dibi\Exception) {
            // Table might already exist
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
        } catch (\Dibi\Exception) {
            // Table might already exist
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
        } catch (\Dibi\Exception) {
            // Table might already exist
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
        parent::setUp();
    }

    /**
     * Refresh test data in the database
     */
    public function refreshData() : void {
        // Delete existing data
        DB::delete(ModelA::TABLE, ['1 = 1']);
        DB::delete(ModelB::TABLE, ['1 = 1']);
        DB::delete(ModelC::TABLE, ['1 = 1']);
        DB::delete(ModelD::TABLE, ['1 = 1']);
        DB::delete(ModelE::TABLE, ['1 = 1']);
        DB::delete(ModelWithTimestamps::TABLE, ['1 = 1']);
        DB::delete(ModelInvalidInstantiate::TABLE, ['1 = 1']);
        DB::delete('modelsD_modelsE', ['1 = 1']);

        // Insert test data for ModelA
        DB::insert(
            ModelA::TABLE,
            [
                'model_a_id' => 1,
                'name'       => 'model1',
                'age'        => 20,
                'verified'   => false,
            ]
        );

        DB::insert(
            ModelA::TABLE,
            [
                'model_a_id' => 2,
                'name'       => 'model2',
                'age'        => null,
                'verified'   => true,
            ]
        );

        // Insert test data for ModelE
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

        // Insert test data for ModelD
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

        // Set up many-to-many relationships
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

        $this->cache->clean([Cache::All => true]);
        ModelRepository::clearInstances();
    }

    public function tearDown() : void {
        DB::close();
        parent::tearDown();
    }

    /**
     * Test many-to-many relation updates
     */
    public function testManyToManyUpdate() : void {
        $this->refreshData();

        // Fetch the models
        $modelD = ModelD::get(1);
        $modelE1 = ModelE::get(1);
        $modelE2 = ModelE::get(2);
        $modelE3 = ModelE::get(3);

        // Initial state: modelD should have 3 related modelEs
        self::assertCount(3, $modelD->models);
        self::assertTrue($modelD->models->contains($modelE1));
        self::assertTrue($modelD->models->contains($modelE2));
        self::assertTrue($modelD->models->contains($modelE3));

        // Remove one relation
        $modelD->models->remove($modelE2);
        self::assertCount(2, $modelD->models);

        // Save the changes
        self::assertTrue($modelD->save());

        // Reload the model to verify changes persisted
        ModelE::clearInstances();
        ModelD::clearInstances();
        $modelD = ModelD::get(1);
        $modelE2 = ModelE::get(2);

        self::assertCount(2, $modelD->models);
        self::assertFalse($modelD->models->contains($modelE2));

        // Add a relation back
        $modelD->models->add($modelE2);
        self::assertCount(3, $modelD->models);
        self::assertTrue($modelD->save());

        // Reload and verify
        ModelE::clearInstances();
        ModelD::clearInstances();
        $modelD = ModelD::get(1);
        $modelE2 = ModelE::get(2);

        self::assertCount(3, $modelD->models);
        self::assertTrue($modelD->models->contains($modelE2));
    }

    /**
     * Test tracking changed properties
     */
    public function testChangedTrackingProperties() : void {
        $this->refreshData();

        // Get a model
        $model = ModelA::get(1);

        // No properties changed initially
        self::assertFalse($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));

        // Change a property
        $model->name = 'new name';
        self::assertTrue($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));

        // Change another property
        $model->age = 30;
        self::assertTrue($model->hasChanged('name'));
        self::assertTrue($model->hasChanged('age'));

        // Save the changes
        self::assertTrue($model->save());

        // After saving, no properties should be marked as changed
        self::assertFalse($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));

        // Verify the changes were actually saved
        ModelA::clearInstances();
        $reloadedModel = ModelA::get($model->id);
        self::assertEquals('new name', $reloadedModel->name);
        self::assertEquals(30, $reloadedModel->age);
    }

    /**
     * Test the protected updateManyToManyRelations method directly
     */
    public function testUpdateManyToManyRelations() : void {
        $this->refreshData();

        // Create a protected test method wrapper to access protected method
        $modelD = ModelD::get(1);
        $reflection = new \ReflectionObject($modelD);
        $method = $reflection->getMethod('updateManyToManyRelations');
        $method->setAccessible(true);

        // Test the method directly
        $result = $method->invoke($modelD, false);
        self::assertTrue($result);

        // Modify the relations
        $modelE = ModelE::get(1);
        $modelD->models->remove($modelE);

        // Update relations and check result
        $result = $method->invoke($modelD, false);
        self::assertTrue($result);

        // Verify changes were saved
        ModelE::clearInstances();
        ModelD::clearInstances();
        $reloadedModelD = ModelD::get(1);
        self::assertCount(2, $reloadedModelD->models);
        self::assertFalse($reloadedModelD->models->contains($modelE));
    }
}
