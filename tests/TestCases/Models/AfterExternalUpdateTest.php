<?php

declare(strict_types=1);

namespace TestCases\Models;

use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use PHPUnit\Framework\TestCase;

/**
 * Tests for additional model features
 */
class AfterExternalUpdateTest extends TestCase
{
    use DbHelpers;

    public function setUp() : void {
        $this->initDb('dbExternalUpdate');

        parent::setUp();
    }

    public function tearDown() : void {
        $this->cleanupDb();
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

        // Add a relation back to reset the state
        $modelD->models->add($modelE);
        self::assertTrue($modelD->save());

        // The hook should have been called again
        self::assertEquals(2, ModelE::$hookCallCount);
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
