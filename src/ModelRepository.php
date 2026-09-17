<?php

declare(strict_types=1);

namespace Lsr\Orm;

use Lsr\Orm\Attributes\ColumnType;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Interfaces\LoadedModel;
use Lsr\Orm\Lifecycle\ModelLifecycleHookInterface;
use Lsr\Orm\Lifecycle\ModelLifecycleScopeInterface;
use Lsr\Orm\Logging\LsrModelLoggerProvider;
use Lsr\Orm\Logging\ModelLoggerProviderInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Throwable;

final class ModelRepository
{
    /**
     * @var array<class-string<Model>, array<int, Model&LoadedModel>>
     */
    private static array $instances = [];

    /** @var array<class-string<Model>, LoggerInterface> */
    private static array $loggers = [];

    private static ?ModelLoggerProviderInterface $loggerProvider = null;

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
    /** @var array<class-string<Model>, array<string, ColumnType|null>> Resolved property column types */
    public static array $columnTypes = [];

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
        if ( ! $model->isLoaded()) {
            return;
        }
        /** @var Model&LoadedModel $model */
        assert($model->id !== null);
        self::$instances[$model::class] ??= [];
        self::$instances[$model::class][$model->id] = $model;
    }

    public static function removeInstance(Model $model): void {
        if ( ! $model->isLoaded()) {
            return;
        }
        if (isset(self::$instances[$model::class][$model->id])) {
            unset(self::$instances[$model::class][$model->id]);
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @return LoggerInterface
     */
    public static function getLogger(string $class): LoggerInterface {
        self::$loggers[$class] ??= (self::$loggerProvider ??= new LsrModelLoggerProvider())->getLogger($class);
        return self::$loggers[$class];
    }

    /**
     * Select the provider for future logger lookups; null restores the standalone default.
     * Models that already acquired a logger retain it for their lifetime.
     */
    public static function setLoggerProvider(?ModelLoggerProviderInterface $provider): void {
        self::$loggerProvider = $provider;
        self::clearLoggers();
    }

    /**
     * @param  class-string<Model>|null  $class
     * @return void
     */
    public static function clearInstances(?string $class = null): void {
        TranslationCollection::clearInstances($class);
        if (isset($class)) {
            foreach (self::$instances[$class] ?? [] as $id => $model) {
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
        // Do not reset the selected provider or loggers retained by existing models.
        self::$loggers = [];
    }
}
