<?php

declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Orm\Lifecycle\ModelLifecycleEvent;
use Lsr\Orm\ModelRepository;
use Mocks\Models\QueryModel;
use PHPUnit\Framework\TestCase;

final class ModelLifecycleTest extends TestCase
{
    use DbHelpers;

    protected function setUp(): void {
        $this->initDb('dbModelLifecycle');
        ModelRepository::setLifecycleHook(null);
    }

    protected function tearDown(): void {
        ModelRepository::setLifecycleHook(null);
        $this->cleanupDb();
    }

    public function testQueryAndHydrationCaptureAreIndependent(): void {
        $queryHook = new RecordingModelLifecycleHook([ModelLifecycleEvent::QUERY]);
        ModelRepository::setLifecycleHook($queryHook);

        self::assertSame(4, QueryModel::query()->count(cache: false));
        self::assertCount(4, QueryModel::query()->get(cache: false));
        ModelRepository::clearInstances(QueryModel::class);
        self::assertSame(1, QueryModel::get(1)->id);

        self::assertSame(
            [ModelLifecycleEvent::COUNT, ModelLifecycleEvent::GET, ModelLifecycleEvent::FETCH],
            array_map(static fn(ModelLifecycleEvent $event): string => $event->operation, $queryHook->events),
        );
        self::assertSame(4, $queryHook->events[0]->resultCount);
        self::assertSame(4, $queryHook->events[1]->resultCount);
        self::assertSame(1, $queryHook->events[2]->resultCount);

        $hydrationHook = new RecordingModelLifecycleHook([ModelLifecycleEvent::HYDRATION]);
        ModelRepository::setLifecycleHook($hydrationHook);
        ModelRepository::clearInstances(QueryModel::class);

        self::assertCount(4, QueryModel::query()->get(cache: false));
        self::assertCount(4, $hydrationHook->events);
        foreach ($hydrationHook->events as $event) {
            self::assertSame(ModelLifecycleEvent::HYDRATE, $event->operation);
            self::assertSame(QueryModel::class, $event->modelClass);
            self::assertArrayNotHasKey('modelId', get_object_vars($event));
            self::assertArrayNotHasKey('table', get_object_vars($event));
        }
    }

    public function testMutationCaptureReportsLifecycleOutcomes(): void {
        $hook = new RecordingModelLifecycleHook([ModelLifecycleEvent::MUTATION]);
        ModelRepository::setLifecycleHook($hook);
        $model = new QueryModel();
        $model->name = 'telemetry';
        $model->age = 10;

        self::assertTrue($model->insert());
        $model->age = 11;
        self::assertTrue($model->update());
        self::assertTrue($model->delete());

        self::assertSame(
            [ModelLifecycleEvent::INSERT, ModelLifecycleEvent::UPDATE, ModelLifecycleEvent::DELETE],
            array_map(static fn(ModelLifecycleEvent $event): string => $event->operation, $hook->events),
        );
        foreach ($hook->events as $event) {
            self::assertSame(ModelLifecycleEvent::SUCCESS, $event->outcome);
            self::assertGreaterThanOrEqual(0.0, $event->durationSeconds);
        }
    }

    public function testHookFailureDoesNotAffectModelOperations(): void {
        ModelRepository::setLifecycleHook(
            new RecordingModelLifecycleHook([ModelLifecycleEvent::QUERY], fail: true),
        );

        self::assertSame(4, QueryModel::query()->count(cache: false));
    }
}
