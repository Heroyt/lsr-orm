<?php
declare(strict_types=1);

namespace TestCases\Models;

use Mocks\Models\ModelA;
use Mocks\Models\ModelB;
use Mocks\Models\ModelC;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use PHPUnit\Framework\TestCase;

class ChangeCheckingTest extends TestCase
{
    use DbHelpers;

    public function setUp() : void {
        $this->initDb('dbChangeChecking');

        parent::setUp();
    }

    public function tearDown() : void {
        $this->cleanupDb();
        parent::tearDown();
    }

    public function testGetChangedProperties() : void {
        $model = ModelA::get(1);
        self::assertEmpty($model->getChangedProperties());

        $model->name = 'New Name';
        self::assertArrayHasKey('name', $model->getChangedProperties());

        $model->age = 30;
        self::assertArrayHasKey('age', $model->getChangedProperties());

        $model->verified = true;
        self::assertArrayHasKey('verified', $model->getChangedProperties());

        $child = $model->children->first();
        self::assertNotNull($child, 'Child should exist');
        $model->children->remove($child);
        self::assertArrayHasKey('children', $model->getChangedProperties());
    }

    public function testSimpleChange() : void {
        $model = ModelA::get(1);
        self::assertFalse($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));
        self::assertFalse($model->hasChanged('verified'));
        self::assertFalse($model->hasChanged('children'));

        $originalName = $model->name;
        $model->name = 'New Name';
        self::assertTrue($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));
        self::assertFalse($model->hasChanged('verified'));
        self::assertFalse($model->hasChanged('children'));

        // Reset back to original value
        $model->name = $originalName;
        self::assertFalse($model->hasChanged('name'));
        self::assertFalse($model->hasChanged('age'));
        self::assertFalse($model->hasChanged('verified'));
        self::assertFalse($model->hasChanged('children'));
    }

    public function testInsertExtendChange() : void {
        $model = ModelC::get(1);
        self::assertFalse($model->hasChanged('value0'));
        self::assertFalse($model->hasChanged('data'));

        $originalValue = clone $model->data;
        $model->data->value1 = 'New value';
        self::assertTrue($model->hasChanged('data'));
        self::assertFalse($model->hasChanged('value0'));

        // Reset back to original value
        $model->data = $originalValue;
        self::assertFalse($model->hasChanged('data'));
        self::assertFalse($model->hasChanged('value0'));
    }

    public function testOneToManyRelationChange() : void {
        $model = ModelA::get(1);
        self::assertFalse($model->hasChanged('children'));

        // Remove a child
        $child = $model->children->first();
        self::assertNotNull($child, 'Child should exist');
        $model->children->remove($child);
        self::assertTrue(
            $model->hasChanged('children'),
            'Children relation should be marked as changed after removing a child'
        );

        // Add the child back
        $model->children->add($child);
        self::assertFalse(
            $model->hasChanged('children'),
            'Children relation should not be marked as changed after adding the same child back'
        );
    }

    public function testManyToOneRelationChange() : void {
        $model = ModelB::get(1);
        self::assertFalse($model->hasChanged('parent'));

        // Remove a parent
        $parent = $model->parent;
        self::assertNotNull($parent, 'Parent should exist');
        $model->parent = null;
        self::assertTrue(
            $model->hasChanged('parent'),
            'Parent relation should be marked as changed after removing the parent'
        );

        // Add parent back
        $model->parent = $parent;
        self::assertFalse(
            $model->hasChanged('parent'),
            'Parent relation should not be marked as changed after adding the parent back'
        );

        // Change to a different parent
        $newParent = ModelA::query()->where('model_a_id <> %i', $model->id)->first();
        self::assertNotNull($newParent, 'New parent should exist');
        $model->parent = $newParent;
        self::assertTrue(
            $model->hasChanged('parent'),
            'Parent relation should be marked as changed after changing to a different parent'
        );
    }

    public function testManyToManyRelationChange() : void {
        $model = ModelD::get(2);
        self::assertFalse($model->hasChanged('models'));

        // Remove a relation
        $relation = $model->models->first();
        self::assertNotNull($relation, 'Relation should exist');
        $model->models->remove($relation);
        self::assertTrue(
            $model->hasChanged('models'),
            'Relation should be marked as changed after removing the models'
        );

        // Add relation back
        $model->models->add($relation);
        self::assertFalse(
            $model->hasChanged('models'),
            'Relation should not be marked as changed after adding the same model back'
        );

        // Add a new relation
        $newRelation = ModelE::query()
                             ->where('model_e_id NOT IN %in', $model->models->map(static fn(ModelE $e) => $e->id))
                             ->first();
        self::assertNotNull($newRelation, 'New relation should exist');
        $model->models->add($newRelation);
        self::assertTrue($model->hasChanged('models'), 'Relation should be marked as changed after adding a new model');
    }

    public function testChangeAfterSave() : void {
        $model = ModelA::get(1);
        self::assertFalse($model->hasChanged('name'));

        // Change the name and save
        $model->name = 'Changed Name';
        self::assertTrue($model->hasChanged('name'), 'Name should be marked as changed before saving');
        self::assertTrue($model->save(), 'Save failed');

        // Check if the change is still tracked after save
        self::assertFalse($model->hasChanged('name'), 'Name should not be marked as changed after save');
    }

    public function testInsertExtendChangeAfterSave() : void {
        $model = ModelC::get(1);
        self::assertFalse($model->hasChanged('value0'));
        self::assertFalse($model->hasChanged('data'));

        $model->data->value1 = 'New value';
        self::assertTrue($model->hasChanged('data'));
        self::assertFalse($model->hasChanged('value0'));

        // Save the model
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse($model->hasChanged('data'), 'Data should not be marked as changed after save');
    }

    public function testOneToManyRelationChangeAfterSave() : void {
        $model = ModelA::get(1);
        self::assertFalse($model->hasChanged('children'));

        // Remove a child
        $child = $model->children->first();
        self::assertNotNull($child, 'Child should exist');
        $model->children->remove($child);
        self::assertTrue(
            $model->hasChanged('children'),
            'Children relation should be marked as changed after removing a child'
        );
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse(
            $model->hasChanged('children'),
            'Children relation should not be marked as changed after save'
        );
    }

    public function testManyToOneRelationChangeAfterSave() : void {
        $model = ModelB::get(1);
        self::assertFalse($model->hasChanged('parent'));

        // Remove a parent
        $parent = $model->parent;
        self::assertNotNull($parent, 'Parent should exist');
        $model->parent = null;
        self::assertTrue($model->hasChanged('parent'), 'Parent relation should be marked as changed');
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse($model->hasChanged('parent'), 'Parent relation should not be marked as changed after save');

        // Change to a different parent
        $newParent = ModelA::query()->where('model_a_id <> %i', $model->id)->first();
        self::assertNotNull($newParent, 'New parent should exist');
        $model->parent = $newParent;
        self::assertTrue(
            $model->hasChanged('parent'),
            'Parent relation should be marked as changed after changing to a different parent'
        );
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse($model->hasChanged('parent'), 'Parent relation should not be marked as changed after save');
    }

    public function testManyToManyRelationChangeAfterSave() : void {
        $model = ModelD::get(2);
        self::assertFalse($model->hasChanged('models'));

        // Remove a relation
        $relation = $model->models->first();
        self::assertNotNull($relation, 'Relation should exist');
        $model->models->remove($relation);
        self::assertTrue(
            $model->hasChanged('models'),
            'Relation should be marked as changed after removing the models'
        );
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse($model->hasChanged('models'), 'Relation should not be marked as changed after save');

        // Add a new relation
        $newRelation = ModelE::query()
                             ->where('model_e_id NOT IN %in', $model->models->map(static fn(ModelE $e) => $e->id))
                             ->first();
        self::assertNotNull($newRelation, 'New relation should exist');
        $model->models->add($newRelation);
        self::assertTrue($model->hasChanged('models'), 'Relation should be marked as changed after adding a new model');
        self::assertTrue($model->save(), 'Save failed');
        self::assertFalse($model->hasChanged('models'), 'Relation should not be marked as changed after save');
    }

}
