<?php

declare(strict_types=1);

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Orm;

use InvalidArgumentException;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Orm\Attributes\Relations\Translations;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Interfaces\LoadedModel;
use Lsr\Orm\Lifecycle\ModelLifecycleEvent;
use Throwable;

/**
 * @template T of Model
 */
class ModelQuery
{
    protected Fluent $query;

    /** @var array<string, list<string>> */
    private array $translationLocales = [];

    /**
     * Preload a locale-keyed relation without changing parent filtering or pagination.
     *
     * @param list<string> $locales
     * @return $this
     */
    public function withTranslations(array $locales, string $property = 'translations'): static {
        TranslationCollection::validateLocales($locales);
        $relation = $this->className::getModelConfig()->properties[$property]['relation'] ?? null;
        if (($relation['type'] ?? null) !== Translations::class) {
            throw new InvalidArgumentException('The requested property is not a Translations relation.');
        }
        $this->translationLocales[$property] = array_values(array_unique([
            ...($this->translationLocales[$property] ?? []),
            ...$locales,
        ]));
        return $this;
    }

    /** @param array<int, T&LoadedModel> $models */
    private function preloadTranslations(array $models): void {
        foreach ($this->translationLocales as $property => $locales) {
            $collections = [];
            foreach ($models as $model) {
                $collection = $model->$property;
                assert($collection instanceof TranslationCollection);
                $collections[] = $collection;
            }
            TranslationCollection::preload($collections, $locales);
        }
    }

    /**
     * @param  class-string<T>  $className
     */
    public function __construct(
        protected string $className,
    ) {
        $this->query = DB::select([$this->className::TABLE, 'a'], 'a.*')
            ->cacheTags(
                'models',
                $this->className::TABLE,
                $this->className::TABLE . '/query',
                ...$this->className::CACHE_TAGS,
            );
    }

    /**
     * @param  non-empty-string  ...$tags
     * @return $this
     */
    public function cacheTags(string ...$tags): static {
        $this->query->cacheTags(...$tags);
        return $this;
    }

    /**
     * @param  mixed  ...$cond
     *
     * @return $this
     */
    public function where(...$cond): ModelQuery {
        $this->query->where(...$cond);
        return $this;
    }

    /**
     * @param  int  $limit
     *
     * @return $this
     */
    public function limit(int $limit): ModelQuery {
        $this->query->limit($limit);
        return $this;
    }

    /**
     * @param  int  $offset
     *
     * @return $this
     */
    public function offset(int $offset): ModelQuery {
        $this->query->offset($offset);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function join(...$table): ModelQuery {
        $this->query->join(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function leftJoin(...$table): ModelQuery {
        $this->query->leftJoin(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$table
     *
     * @return $this
     */
    public function rightJoin(...$table): ModelQuery {
        $this->query->rightJoin(...$table);
        return $this;
    }

    /**
     * @param  mixed  ...$cond
     *
     * @return $this
     */
    public function on(...$cond): ModelQuery {
        $this->query->on(...$cond);
        return $this;
    }

    /**
     * @return $this
     */
    public function asc(): ModelQuery {
        $this->query->asc();
        return $this;
    }

    /**
     * @return $this
     */
    public function desc(): ModelQuery {
        $this->query->desc();
        return $this;
    }

    /**
     * @param  mixed  ...$field
     *
     * @return $this
     */
    public function orderBy(...$field): ModelQuery {
        $this->query->orderBy(...$field);
        return $this;
    }

    public function count(bool $cache = true): int {
        $scope = ModelRepository::beginLifecycle(
            ModelLifecycleEvent::QUERY,
            ModelLifecycleEvent::COUNT,
            $this->className,
        );
        try {
            $count = $this->query->count(cache: $cache);
        } catch (Throwable $exception) {
            ModelRepository::completeLifecycle(
                $scope,
                ModelLifecycleEvent::ERROR,
                errorType: $exception::class,
            );
            throw $exception;
        }
        ModelRepository::completeLifecycle(
            $scope,
            ModelLifecycleEvent::SUCCESS,
            $count,
        );
        return $count;
    }

    /**
     * @return (T&LoadedModel)|null
     */
    public function first(bool $cache = true): ?Model {
        $scope = ModelRepository::beginLifecycle(
            ModelLifecycleEvent::QUERY,
            ModelLifecycleEvent::FIRST,
            $this->className,
        );
        try {
            $row = $this->query->fetch(cache: $cache);
            if ( ! isset($row)) {
                $model = null;
            } else {
                /** @var class-string<T&LoadedModel> $className */
                $className = $this->className;
                $model = new $className($row->{$this->className::getPrimaryKey()}, $row);
                $this->preloadTranslations([$model]);
            }
        } catch (Throwable $exception) {
            ModelRepository::completeLifecycle(
                $scope,
                ModelLifecycleEvent::ERROR,
                errorType: $exception::class,
            );
            throw $exception;
        }
        ModelRepository::completeLifecycle(
            $scope,
            ModelLifecycleEvent::SUCCESS,
            $model === null ? 0 : 1,
        );
        return $model;
    }

    /**
     * @return array<int,T&LoadedModel>
     * @throws ValidationException
     */
    public function get(bool $cache = true): array {
        $scope = ModelRepository::beginLifecycle(
            ModelLifecycleEvent::QUERY,
            ModelLifecycleEvent::GET,
            $this->className,
        );
        try {
            $pk = $this->className::getPrimaryKey();
            $rows = $this->query->fetchAll(cache: $cache);
            /** @var class-string<T&LoadedModel> $className */
            $className = $this->className;
            /** @var array<int, T&LoadedModel> $models */
            $models = [];
            foreach ($rows as $row) {
                assert(is_int($row->$pk));
                try {
                    $models[$row->{$pk}] = $className::get($row->$pk, $row);
                } catch (ModelNotFoundException) {
                }
            }
            $this->preloadTranslations($models);
        } catch (Throwable $exception) {
            ModelRepository::completeLifecycle(
                $scope,
                ModelLifecycleEvent::ERROR,
                errorType: $exception::class,
            );
            throw $exception;
        }
        ModelRepository::completeLifecycle(
            $scope,
            ModelLifecycleEvent::SUCCESS,
            count($models),
        );
        return $models;
    }
}
