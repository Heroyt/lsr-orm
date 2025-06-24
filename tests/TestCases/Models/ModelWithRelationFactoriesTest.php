<?php
declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Orm\ModelCollection;
use Mocks\Models\ModelWithRelationFactories;
use PHPUnit\Framework\TestCase;

class ModelWithRelationFactoriesTest extends TestCase
{
    use DbHelpers;

    public function setUp() : void {
        $this->initDb('dbRelationWithFactories');

        parent::setUp();
    }

    public function tearDown() : void {
        $this->cleanupDb();
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