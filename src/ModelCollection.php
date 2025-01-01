<?php

declare(strict_types=1);

namespace Lsr\Orm;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Lsr\Orm\Exceptions\InvalidCollectionModelException;

/**
 * @template T of Model
 * @implements ArrayAccess<int, T>
 * @implements Iterator<int, T>
 */
class ModelCollection implements Countable, Iterator, ArrayAccess, JsonSerializable
{
    public const string UNKNOWN_MODEL = 'unknown';

    /**
     * @var class-string<T>|'unknown'
     * @phpstan-ignore property.onlyRead
     */
    private string $modelClass {
        get {
    if (!isset($this->modelClass) || $this->modelClass === self::UNKNOWN_MODEL) {
        if (empty($this->models)) {
            $this->modelClass = self::UNKNOWN_MODEL;
            return $this->modelClass;
        }
        /** @phpstan-ignore assign.propertyType, classConstant.nonObject */
        $this->modelClass = first($this->models)::class;
    }
            return $this->modelClass;
        }
    }

    /**
     * @param  array<int,T>  $models
     */
    public function __construct(
        public array $models = [],
    ) {
    }

    public function count(): int {
        return count($this->models);
    }

    /**
     * @return T|false
     */
    public function current(): mixed {
        return current($this->models);
    }

    public function next(): void {
        next($this->models);
    }

    public function key(): ?int {
        return key($this->models);
    }

    public function valid(): bool {
        return isset($this->models[key($this->models)]);
    }

    public function rewind(): void {
        reset($this->models);
    }

    /**
     * @param  int  $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->models[$offset]);
    }

    /**
     * @param  int  $offset
     * @return T
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->models[$offset];
    }

    /**
     * @param  int  $offset
     * @param  T  $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        if (
            $this->modelClass !== self::UNKNOWN_MODEL
            && !($value instanceof $this->modelClass)
        ) {
            throw new InvalidCollectionModelException(
                sprintf('Cannot combine models types in a collection (collection class: "%s")', $this->modelClass),
                InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE
            );
        }
        $this->models[$offset] = $value;
    }

    /**
     * @param  int  $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void {
        unset($this->models[$offset]);
    }

    public function jsonSerialize(): mixed {
        return $this->models;
    }

    /**
     * @param  callable(T $model):bool|null  $filter
     * @return T|null
     */
    public function first(?callable $filter = null): ?Model {
        if ($filter === null) {
            return first($this->models);
        }
        return array_find($this->models, $filter);
    }

    /**
     * @param  callable(T $model):bool|null  $filter
     * @return T|null
     */
    public function last(?callable $filter = null): ?Model {
        if ($filter === null) {
            /** @phpstan-ignore return.type */
            return last($this->models);
        }
        return array_find(
            array_reverse($this->models),
            $filter
        );
    }

    /**
     * @param  callable(T $model):bool  $filter
     * @return ModelCollection<T>
     */
    public function filter(callable $filter): ModelCollection {
        return new ModelCollection(array_filter($this->models, $filter));
    }

    /**
     * @template R
     * @param  callable(T $model):R  $function
     * @return R[]
     */
    public function map(callable $function): array {
        return array_map($function, $this->models);
    }

    /**
     * @param  T  $model
     * @return $this
     */
    public function add(Model $model): ModelCollection {
        if (
            $this->modelClass !== self::UNKNOWN_MODEL
            && !($model instanceof $this->modelClass)
        ) {
            throw new InvalidCollectionModelException(
                sprintf('Cannot combine models types in a collection (collection class: "%s")', $this->modelClass),
                InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE
            );
        }
        if ($model->id === null) {
            throw new InvalidCollectionModelException(
                'Cannot add an uninitialized model (without ID) to a collection.',
                InvalidCollectionModelException::UNINITIALIZED_MODEL_CODE
            );
        }
        $this->models[$model->id] = $model;
        return $this;
    }

    /**
     * @param  T  $model
     * @return $this
     */
    public function remove(Model $model): ModelCollection {
        if ($this->modelClass !== self::UNKNOWN_MODEL && !($model instanceof $this->modelClass)) {
            throw new InvalidCollectionModelException(
                sprintf('Invalid model type for the collection (collection class: "%s")', $this->modelClass),
                InvalidCollectionModelException::INVALID_MODEL_TYPE_CODE
            );
        }
        if ($model->id === null || !isset($this->models[$model->id])) {
            return $this;
        }
        unset($this->models[$model->id]);
        return $this;
    }
}
