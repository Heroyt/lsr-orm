<?php

declare(strict_types=1);

namespace Lsr\Orm;

use InvalidArgumentException;
use LogicException;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Config\ModelConfig;
use WeakMap;

/**
 * An owned relation with explicit persistence and lifecycle-scoped lookup caching.
 *
 * @template-covariant T of Model
 * @phpstan-import-type RelationConfig from ModelConfig
 */
final class TranslationCollection
{
    /** @var WeakMap<self<Model>, true>|null */
    private static ?WeakMap $collections = null;

    /** @var array<string, T|null> */
    private array $translations = [];

    /** @var class-string<T> */
    private readonly string $class;
    private readonly string $mappedBy;
    private readonly string $localeProperty;
    private readonly string $foreignKey;

    /**
     * Constructed by the ORM from validated relation metadata.
     *
     * @param RelationConfig $relation
     * @internal
     */
    public function __construct(private readonly Model $parent, array $relation) {
        /** @var class-string<T> $class */
        $class = $relation['class'];
        $this->class = $class;
        $this->mappedBy = $relation['mappedBy'] ?? throw new InvalidArgumentException('Missing translation parent mapping.');
        $this->localeProperty = $relation['localeProperty'] ?? throw new InvalidArgumentException('Missing translation locale mapping.');
        $this->foreignKey = $relation['foreignKey'];
        if (self::$collections === null) {
            /** @var WeakMap<self<Model>, true> $collections */
            $collections = new WeakMap();
            self::$collections = $collections;
        }
        self::$collections[$this] = true;
    }

    /** @return T|null */
    public function find(string $locale): ?Model {
        self::validateLocale($locale);
        if ($this->parent->id === null) {
            return null;
        }
        if ( ! array_key_exists($locale, $this->translations)) {
            self::preload([$this], [$locale]);
        }
        return $this->translations[$locale] ?? null;
    }

    /**
     * Select a whole row; the returned model retains its actual locale.
     *
     * @param list<string> $locales Ordered preference list supplied by the application.
     * @return T|null
     */
    public function resolve(array $locales): ?Model {
        self::validateLocales($locales);
        if ($this->parent->id === null) {
            return null;
        }
        self::preload([$this], $locales);
        foreach ($locales as $locale) {
            if (isset($this->translations[$locale])) {
                return $this->translations[$locale];
            }
        }
        return null;
    }

    /**
     * Return a new unsaved row. Database uniqueness also guards concurrent creates.
     *
     * @return T
     */
    public function create(string $locale): Model {
        self::validateLocale($locale);
        if ($this->parent->id === null) {
            throw new LogicException('Save the parent before creating a translation.');
        }
        // A fresh check also sees another writer's row after a previously cached miss.
        unset($this->translations[$locale]);
        if ($this->find($locale) !== null) {
            throw new LogicException('A translation already exists for this locale.');
        }
        $model = new $this->class();
        $model->{$this->mappedBy} = $this->parent;
        $model->{$this->localeProperty} = $locale;
        return $model;
    }

    /**
     * @param array<array-key, mixed> $locales
     * @internal
     */
    public static function validateLocales(array $locales): void {
        foreach ($locales as $locale) {
            if ( ! is_string($locale)) {
                throw new InvalidArgumentException('Translation locales must be strings.');
            }
            self::validateLocale($locale);
        }
    }

    private static function validateLocale(string $locale): void {
        if (trim($locale) === '') {
            throw new InvalidArgumentException('A translation locale must not be empty or whitespace-only.');
        }
    }

    /**
     * Load only missing locales in bounded batches. Queries deliberately bypass the
     * shared SQL cache: lsr/db has no public mutation-invalidation interface.
     *
     * @param list<self<Model>> $collections Collections with the same relation mapping.
     * @param list<string> $locales
     * @internal
     */
    public static function preload(array $collections, array $locales): void {
        self::validateLocales($locales);
        if ($collections === [] || $locales === []) {
            return;
        }
        $first = $collections[0];
        /** @var array<int, list<self<Model>>> $byParent */
        $byParent = [];
        $neededLocales = [];
        foreach ($collections as $collection) {
            $id = $collection->parent->id;
            if ($id === null) {
                continue;
            }
            $missing = false;
            foreach ($locales as $locale) {
                if ( ! array_key_exists($locale, $collection->translations)) {
                    $neededLocales[$locale] = $locale;
                    $missing = true;
                }
            }
            if ($missing) {
                $byParent[$id][] = $collection;
            }
        }
        if ($byParent === []) {
            return;
        }
        $class = $first->class;
        $localeColumn = Strings::toSnakeCase($first->localeProperty);
        foreach (array_chunk(array_keys($byParent), 200) as $ids) {
            foreach (array_chunk(array_values($neededLocales), 100) as $chunkLocales) {
                $rows = DB::select($class::TABLE, '*')
                    ->where('%n IN %in AND %n IN %in', $first->foreignKey, $ids, $localeColumn, $chunkLocales)
                    ->fetchAll(cache: false);
                // Publish misses only after the query and hydration both succeed.
                $loaded = [];
                foreach ($rows as $row) {
                    $id = $row->{$first->foreignKey};
                    $locale = $row->$localeColumn;
                    if ( ! is_int($id) || ! is_string($locale) || ! isset($byParent[$id])) {
                        throw new LogicException('Invalid translation key returned by the database.');
                    }
                    if (isset($loaded[$id][$locale])) {
                        throw new LogicException('Duplicate translation rows: add a unique parent/locale constraint.');
                    }
                    $pk = $row->{$class::getPrimaryKey()};
                    assert(is_int($pk));
                    $loaded[$id][$locale] = $class::get($pk, $row);
                }
                foreach ($ids as $id) {
                    foreach ($byParent[$id] as $collection) {
                        foreach ($chunkLocales as $locale) {
                            if ( ! array_key_exists($locale, $collection->translations)) {
                                $collection->translations[$locale] = $loaded[$id][$locale] ?? null;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Clear hits and misses even on collections retained outside ModelRepository.
     *
     * @param class-string<Model>|null $class
     * @internal
     */
    public static function clearInstances(?string $class = null): void {
        if (self::$collections === null) {
            return;
        }
        foreach (self::$collections as $collection => $_) {
            if ($class === null || $class === $collection->class || $class === $collection->parent::class) {
                $collection->translations = [];
            }
        }
    }

    /** @internal */
    public static function invalidate(Model $model): void {
        if (self::$collections === null) {
            return;
        }
        foreach (self::$collections as $collection => $_) {
            // Table-wide invalidation also handles reassignment and alternate model classes.
            if ($model::TABLE === $collection->class::TABLE || $model::TABLE === $collection->parent::TABLE) {
                $collection->translations = [];
            }
        }
    }
}
