<?php

declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use Nette\Caching\Storages\DevNullStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests for additional model features
 */
class AfterExternalUpdateTest extends TestCase
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
                    'database' => ROOT."tests/tmp/dbModelsExtarnalUpdate.db",
                    'prefix'   => "",
                ]
            )
        );

        // Create necessary database tables
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

        $this->refreshData();
        parent::setUp();
    }

    /**
     * Refresh test data in the database
     */
    public function refreshData() : void {
        // Delete existing data
        DB::delete(ModelD::TABLE, ['1 = 1']);
        DB::delete(ModelE::TABLE, ['1 = 1']);
        DB::delete('modelsD_modelsE', ['1 = 1']);

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

        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown() : void {
        DB::close();
        parent::tearDown();
    }

    /**
     * Test the AfterExternalUpdate hook functionality using the proper attribute implementation
     */
    public function testAfterExternalUpdate() : void {
        $this->refreshData();

        // Reset hook tracking variables
        ModelE::$hookCallCount = 0;
        ModelE::$lastHookId = null;

        // Get a model and its related model
        $modelD = ModelD::get(1);
        $modelE = ModelE::get(1);

        // Initially the hook hasn't been called
        self::assertEquals(0, ModelE::$hookCallCount);
        self::assertNull(ModelE::$lastHookId);

        // Test that removing a relation triggers the hook
        $modelD->models->remove($modelE);
        self::assertTrue($modelD->save());

        // The hook should have been called once with the correct ID
        self::assertEquals(1, ModelE::$hookCallCount);
        self::assertEquals(1, ModelE::$lastHookId);

        // Reset for next test
        ModelE::$hookCallCount = 0;
        ModelE::$lastHookId = null;

        // Test that adding a duplicate relation does not trigger the hook
        $modelD->models->add($modelE);
        self::assertTrue($modelD->save());

        // The hook should have been called again
        self::assertEquals(0, ModelE::$hookCallCount);
        self::assertEquals(null, ModelE::$lastHookId);
    }
}
