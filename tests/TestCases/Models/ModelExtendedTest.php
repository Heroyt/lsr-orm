<?php

declare(strict_types=1);

namespace TestCases\Models;

use Mocks\Models\ModelA;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use PHPUnit\Framework\TestCase;

/**
 * Extended test suite for models covering additional features
 */
class ModelExtendedTest extends TestCase
{
    use DbHelpers;

    public function setUp() : void {
        $this->initDb('dbExtendedModels');

        parent::setUp();
    }

    public function tearDown() : void {
        $this->cleanupDb();
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
