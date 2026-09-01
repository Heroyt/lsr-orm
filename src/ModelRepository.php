<?php

declare(strict_types=1);

namespace Lsr\Orm;

use Lsr\Logging\Logger;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Interfaces\LoadedModel;
use Lsr\Orm\Lifecycle\ModelLifecycleHookInterface;
use Lsr\Orm\Lifecycle\ModelLifecycleScopeInterface;
use ReflectionClass;
use Throwable;

final class ModelRepository
{
    /**
     * @var array<class-string<Model>, array<int, Model&LoadedModel>>
     */
    private static array $instances = [];

    /** @var array<class-string<Model>, Logger> */
    private static array $loggers = [];

    private static ?ModelLifecycleHookInterface $lifecycleHook = null;

    /** @var array<class-string<Model>, string> */
    public static array $cacheFileName = [];
    /** @var array<class-string<Model>, string> */
    public static array $cacheClassName = [];
    /** @var array<class-string<Model>, ModelConfig> */
    public static array $modelConfig = [];

    /** @var string|null If the config file is currently generating, this is set to the Model's class name. */
    public static ?string $generatingConfig = null;

    /** @var string[] Primary key cache */
    public static array $primaryKeys = [];

    /** @var array<class-string<Model>,ReflectionClass<Model>> */
    public static array $reflections = [];
    /** @var Factory[] */
    public static array $factory = [];

    public static function setLifecycleHook(?ModelLifecycleHookInterface $hook): void {
        self::$lifecycleHook = $hook;
    }

    /**
     * @param class-string<Model> $modelClass
     * @internal
     */
    public static function beginLifecycle(
        string $category,
        string $operation,
        string $modelClass,
    ): ?ModelLifecycleScopeInterface {
        if (self::$lifecycleHook === null) {
            return null;
        }
        try {
            return self::$lifecycleHook->captures($category)
                ? self::$lifecycleHook->begin($category, $operation, $modelClass)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @internal */
    public static function completeLifecycle(
        ?ModelLifecycleScopeInterface $scope,
        string $outcome,
        ?int $resultCount = null,
        ?string $errorType = null,
    ): void {
        try {
            $scope?->complete($outcome, $resultCount, $errorType);
        } catch (Throwable) {
            // Lifecycle hooks must never affect model behavior.
        }
    }

    /**
     * @template T of Model
     * @param  class-string<T>  $class
     * @param  int  $id
     * @return (T&LoadedModel)|null
     */
    public static function getInstance(string $class, int $id): ?Model {
        self::$instances[$class] ??= [];
        /** @var array<int,T&LoadedModel> $instances */
        $instances = self::$instances[$class];
        return $instances[$id] ?? null;
    }

    /**
     * @param Model $model
     * @return void
     */
    public static function setInstance(Model $model): void {
        if (!$model->isLoaded()) {
            return;
        }
        /** @var Model&LoadedModel $model */
        assert($model->id !== null);
        self::$instances[$model::class] ??= [];
        self::$instances[$model::class][$model->id] = $model;
    }

    public static function removeInstance(Model $model): void {
        if (!$model->isLoaded()) {
            return;
        }
        if (isset(self::$instances[$model::class][$model->id])) {
            unset(self::$instances[$model::class][$model->id]);
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @return Logger
     */
    public static function getLogger(string $class): Logger {
        self::$loggers[$class] ??= new Logger(LOG_DIR . 'models/', $class::TABLE);
        return self::$loggers[$class];
    }

    /**
     * @param  class-string<Model>|null  $class
     * @return void
     */
    public static function clearInstances(?string $class = null): void {
        if (isset($class)) {
            foreach (self::$instances[$class] as $id => $model) {
                unset(self::$instances[$class][$id]);
            }
            self::$instances[$class] = [];
            return;
        }

        // Clear all instances
        foreach (self::$instances as $classKey => $models) {
            foreach ($models as $id => $model) {
                unset(self::$instances[$classKey][$id]);
            }
            self::$instances[$classKey] = [];
        }
    }

    public static function clearLoggers(): void {
        foreach (self::$loggers as $class => $loggers) {
            unset(self::$loggers[$class]);
        }
        self::$loggers = [];
    }
}
