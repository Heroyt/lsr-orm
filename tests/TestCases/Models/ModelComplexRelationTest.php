<?php

namespace TestCases\Models;

use Mocks\Models\ModelCA;
use Mocks\Models\ModelCB;
use Mocks\Models\ModelCC;
use PHPUnit\Framework\TestCase;

class ModelComplexRelationTest extends TestCase
{
    use DbHelpers;

    public function setUp(): void {
        $this->initDb('dbComplexRelations');

        parent::setUp();
    }

    public function tearDown(): void {
        $this->cleanupDb();
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
