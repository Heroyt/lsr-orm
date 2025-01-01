<?php

declare(strict_types=1);

namespace TestCases;

use Faker\Factory;
use Lsr\Orm\Exceptions\InvalidCollectionModelException;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;
use Mocks\Models\ModelA;
use Mocks\Models\ModelB;
use Mocks\Models\TestEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelCollectionTest extends TestCase
{
    public static function getTestCollections(): \Generator {
        $faker = Factory::create();

        $data = [];
        for ($i = 1; $i <= 20; $i++) {
            $model = new ModelA();
            $model->id = $i;
            $model->name = $faker->name();
            $model->age = $faker->numberBetween(1, 99);

            $childCount = $faker->numberBetween(1, 5);
            for ($j = 1; $j <= $childCount; $j++) {
                $child = new ModelB();
                $child->id = $j;
                $child->description = $faker->text();
                $child->modelType = $faker->randomElement(TestEnum::cases());
                $child->parent = $model;
                $model->children->add($child);
            }

            $data[$i] = $model;
        }
        $collection = new ModelCollection($data);
        yield '20 models' => [
            'collection' => $collection,
            'data' => $data,
            'expectedCount' => 20,
        ];

        $model = new ModelA();
        $model->id = 50;
        $model->name = $faker->name();
        $model->age = $faker->numberBetween(1, 99);
        $data = [
          50 => $model,
        ];
        $collection = new ModelCollection($data);
        yield '1 model' => [
            'collection' => $collection,
            'data' => $data,
            'expectedCount' => 1,
        ];
        $data = [];
        $collection = new ModelCollection($data);
        yield 'empty' => [
            'collection' => $collection,
            'data' => $data,
            'expectedCount' => 0,
        ];
    }

    public static function getAddModel(): \Generator {
        $model = new ModelA();
        $model->id = 99;
        foreach (self::getTestCollections() as $key => ['collection' => $collection, 'expectedCount' => $expectedCount, 'data' => $data]) {
            yield $key => [
                'collection' => $collection,
                'expectedCount' => $expectedCount,
                'model' => $model,
                'id' => $model->id
            ];
        }
    }

    public static function getModelRemove(): \Generator {
        $data = [];
        for ($i = 1; $i < 5; $i++) {
            $model = new ModelA();
            $model->id = $i;
            $data[$i] = $model;
        }
        $collection = new ModelCollection($data);
        yield 'Delete 1' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 3,
            'model' => $data[1],
        ];

        $collection = new ModelCollection($data);
        yield 'Delete 2' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 3,
            'model' => $data[2],
        ];

        $collection = new ModelCollection($data);
        yield 'Delete 3' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 3,
            'model' => $data[3],
        ];

        $collection = new ModelCollection($data);
        yield 'Delete 4' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 3,
            'model' => $data[4],
        ];

        $collection = new ModelCollection($data);
        yield 'Without ID' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 4,
            'model' => new ModelA(), // Without ID
        ];

        $collection = new ModelCollection($data);
        $model = new ModelA();
        $model->id = 99;
        yield 'Not in collection' => [
            'collection' => $collection,
            'expectedCount' => 4,
            'expectedCountAfterDelete' => 4,
            'model' => $model,
        ];
    }

    #[DataProvider('getTestCollections')]
    public function testIterator(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);
        foreach ($collection as $id => $model) {
            $this->assertEquals($data[$id], $model);
        }
    }

    #[DataProvider('getTestCollections')]
    public function testArrayAccess(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);

        // Test get
        foreach ($data as $id => $model) {
            $this->assertTrue(isset($collection[$id]));
            $this->assertEquals($model, $collection[$id]);
        }

        // Test set
        $this->assertFalse(isset($collection[99]));
        $model = new ModelA();
        $collection[99] = $model;
        $this->assertCount($expectedCount + 1, $collection);
        $this->assertEquals($model, $collection[99]);

        // Test unset
        unset($collection[99]);
        $this->assertFalse(isset($collection[99]));
        $this->assertCount($expectedCount, $collection);
    }

    public function testInvalidSet(): void {
        $collection = new ModelCollection(
            [
                new ModelA(),
                new ModelA(),
                new ModelA(),
            ]
        );

        $this->expectException(InvalidCollectionModelException::class);
        $this->expectExceptionCode(InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE);
        $collection[99] = new ModelB();
    }

    #[DataProvider('getAddModel')]
    public function testAdd(ModelCollection $collection, int $expectedCount, Model $model, int $id): void {
        $this->assertCount($expectedCount, $collection);

        $collection->add($model);
        $this->assertCount($expectedCount + 1, $collection);
        $this->assertEquals($model, $collection[$id]);
    }

    public function testInvalidModelAdd(): void {
        $collection = new ModelCollection(
            [
                new ModelA(),
                new ModelA(),
                new ModelA(),
            ]
        );

        $this->expectException(InvalidCollectionModelException::class);
        $this->expectExceptionCode(InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE);
        $this->expectExceptionMessage(sprintf('Cannot combine models types in a collection (collection class: "%s")', ModelA::class));
        $collection->add(new ModelB());
    }

    public function testMissingIdAdd(): void {
        $collection = new ModelCollection(
            [
                new ModelA(),
                new ModelA(),
                new ModelA(),
            ]
        );

        $this->expectException(InvalidCollectionModelException::class);
        $this->expectExceptionCode(InvalidCollectionModelException::UNINITIALIZED_MODEL_CODE);
        $this->expectExceptionMessage('Cannot add an uninitialized model (without ID) to a collection.');
        $collection->add(new ModelA());
    }

    #[DataProvider('getModelRemove')]
    public function testRemove(ModelCollection $collection, int $expectedCount, int $expectedCountAfterDelete, Model $model): void {
        $this->assertCount($expectedCount, $collection);
        $collection->remove($model);
        $this->assertCount($expectedCountAfterDelete, $collection);
        if (isset($model->id)) {
            $this->assertFalse(isset($collection[$model->id]));
        }
    }

    public function testInvalidModelRemove(): void {
        $collection = new ModelCollection(
            [
                new ModelA(),
                new ModelA(),
                new ModelA(),
            ]
        );

        $this->expectException(InvalidCollectionModelException::class);
        $this->expectExceptionCode(InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE);
        $this->expectExceptionMessage(sprintf('Invalid model type for the collection (collection class: "%s")', ModelA::class));
        $collection->remove(new ModelB());
    }

    #[DataProvider('getTestCollections')]
    public function testFirst(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);

        $model = $collection->first();
        if ($expectedCount === 0) {
            $this->assertNull($model);
        } else {
            $firstKey = array_key_first($data);
            $this->assertEquals($data[$firstKey], $model);
        }

        // Test with filter
        $model = $collection->first(static fn(ModelA $model) => $model->id > 10);

        if ($expectedCount === 0) {
            $this->assertNull($model);
        } else {
            $this->assertGreaterThan(10, $model->id);
        }
    }

    #[DataProvider('getTestCollections')]
    public function testLast(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);

        $model = $collection->last();
        if ($expectedCount === 0) {
            $this->assertNull($model);
        } else {
            $lastKey = array_key_last($data);
            $this->assertEquals($data[$lastKey], $model);
        }

        // Test with filter
        $model = $collection->last(static fn(ModelA $model) => $model->id > 10);

        if ($expectedCount === 0) {
            $this->assertNull($model);
        } else {
            $this->assertGreaterThan(10, $model->id);
        }
    }

    #[DataProvider('getTestCollections')]
    public function testFilter(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);
        $filterFunc = static fn(ModelA $model) => $model->id > 10;

        $filteredData = array_filter($data, $filterFunc);
        $filteredCollection = $collection->filter($filterFunc);

        $this->assertCount(count($filteredData), $filteredCollection);
        foreach ($filteredData as $id => $model) {
            $this->assertEquals($model, $filteredCollection[$id]);
        }
    }

    #[DataProvider('getTestCollections')]
    public function testMap(ModelCollection $collection, int $expectedCount, array $data): void {
        $this->assertCount($expectedCount, $collection);
        $mapFunc = static fn(ModelA $model) => $model->id;

        $mapData = $collection->map($mapFunc);
        $this->assertCount($expectedCount, $mapData);
        foreach ($data as $id => $model) {
            $this->assertEquals($id, $mapData[$id]);
        }
    }
}
