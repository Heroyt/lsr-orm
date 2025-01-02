<?php

declare(strict_types=1);

namespace Lsr\Orm;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\UndefinedPropertyException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use ReflectionClass;
use RuntimeException;

/**
 * @phpstan-import-type RelationConfig from ModelConfig
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
trait ModelFetch
{
    /**
     * Fetch model's data from DB
     *
     * @param  bool  $refresh
     *
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function fetch(bool $refresh = false) : void {
        if (!isset($this->id) || $this->id <= 0) {
            throw new RuntimeException('Id needs to be set before fetching model\'s data.');
        }
        if ($refresh || !isset($this->row)) {
            /** @var Row|null $row */
            $row = DB::select([$this::TABLE, 'a'], '*')
                     ->where('%n = %i', $this::getPrimaryKey(), $this->id)
                     ->cacheTags(...$this->getCacheTags())
                     ->fetch();
            $this->row = $row;
        }
        if (!isset($this->row)) {
            throw new ModelNotFoundException(get_class($this).' model of ID '.$this->id.' was not found.');
        }
        $this->fillFromRow();
    }

    /**
     * @return void
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    protected function fillFromRow() : void {
        if (!isset($this->row)) {
            return;
        }

        $row = $this->row->toArray();
        foreach ($this::getProperties() as $name => $property) {
            if ($property['isExtend']) {
                /** @var class-string<InsertExtendInterface> $class */
                $class = $property['type'];
                $reflection = new ReflectionClass($class);

                $this->$name = $reflection->newLazyProxy(
                    fn() => $class::parseRow($this->row) // @phpstan-ignore argument.type
                );
                continue;
            }

            if (isset($property['relation'])) {
                $this->processRelation($name, $property['relation'], $property);
                continue;
            }

            if (array_key_exists($name, $row)) {
                $val = $row[$name];
            }
            else {
                $snakeName = Strings::toSnakeCase($name);
                if (!array_key_exists($snakeName, $row)) {
                    // TODO: Maybe throw an exception
                    continue;
                }
                $val = $row[$snakeName];
            }

            if ($property['isPrimaryKey']) {
                assert(is_int($val) || $val === null);
                $this->id = $val;
                continue;
            }

            $this->setProperty($name, $val, $property);
        }
    }


    /**
     * @param  string  $propertyName
     * @param  RelationConfig  $relation
     * @param  PropertyConfig|null  $property
     *
     * @return void
     * @throws ModelNotFoundException
     */
    protected function processRelation(string $propertyName, array $relation, ?array $property = null) : void {
        if (!isset($property)) {
            $property = $this::getProperties()[$propertyName] ?? null;
            if (!isset($property)) {
                throw new UndefinedPropertyException('Undefined property '.$this::class.'::$'.$propertyName);
            }
        }

        /** @var class-string<Model> $className */
        $className = $relation['class'];
        $factory = $className::getFactory();

        $foreignKey = $relation['foreignKey'];
        $localKey = $relation['localKey'];

        switch ($relation['type']) {
            case ManyToOne::class:
            case OneToOne::class:
                /** @var int|null $id */ $id = $this->row?->$localKey;
                if (isset($id)) {
                    $this->relationIds[$propertyName] = $id;
                }

                // Check for nullable relations
                if (is_null($id)) {
                    if (!$property['allowsNull']) {
                        throw new ValidationException('Cannot assign null to a non nullable relation');
                    }
                    $this->$propertyName = null;
                    break;
                }

            if ($relation['factoryMethod'] !== null) {
                $method = $relation['factoryMethod'];
                $factoryClosure = fn() => $this->$method();
            }
            else {
                $factoryClosure = static function () use ($factory, $id, $className, $property) {
                    try {
                        return isset($factory) ?
                            $factory->factoryClass::getById($id, $factory->defaultOptions)
                            : $className::get($id);
                    } catch (ModelNotFoundException $e) {
                        if (!$property['allowsNull']) {
                            throw $e;
                        }
                    }

                    // Default
                    return null;
                };
            }

                if ($relation['loadingType'] === LoadingType::LAZY) {
                    $reflection = new ReflectionClass($className);
                    $this->$propertyName = $reflection->newLazyProxy($factoryClosure);
                    break;
                }

                // Get the relation
                $this->$propertyName = $factoryClosure();
                break;
            case OneToMany::class:
                $id = $this->id;
                /** @var class-string<ModelCollection> $collectionClass */
                $collectionClass = ModelCollection::class;
                if (
                    isset($property['type'])
                    && $property['type'] !== $collectionClass
                    && class_exists($property['type'])
                ) {
                    if (!is_subclass_of($property['type'], ModelCollection::class)) {
                        throw new \RuntimeException(
                            sprintf(
                                'Invalid property type %s for relation type %s on %s::$%s (must extend %s)',
                                $property['type'],
                                $relation['type'],
                                $this::class,
                                $propertyName,
                                ModelCollection::class,
                            )
                        );
                    }
                    $collectionClass = $property['type'];
                }

                if ($id === null) {
                    $this->$propertyName = new $collectionClass();
                    break;
                }
                if ($relation['factoryMethod'] !== null) {
                    $method = $relation['factoryMethod'];
                    $factoryClosure = fn() => $this->$method();
                }
                else {
                    $factoryClosure = fn() => new $collectionClass(
                        $className::query()
                                  ->where('%n = %i', $foreignKey, $id)
                                  ->cacheTags($this::TABLE.'/'.$this->id.'/relations')
                                  ->get()
                    );
                }

                if ($relation['loadingType'] === LoadingType::LAZY) {
                    $reflection = new ReflectionClass($collectionClass);
                    $this->$propertyName = $reflection->newLazyProxy($factoryClosure);
                    break;
                }
                $this->$propertyName = $factoryClosure();
                break;
            case ManyToMany::class:
                $id = $this->id;
                /** @var class-string<ModelCollection> $collectionClass */
                $collectionClass = ModelCollection::class;
                if (
                    isset($property['type'])
                    && $property['type'] !== $collectionClass
                    && class_exists($property['type'])
                ) {
                    if (!is_subclass_of($property['type'], $collectionClass)) {
                        throw new \RuntimeException(
                            sprintf(
                                'Invalid property type %s for relation type %s on %s::$%s (must extend %s)',
                                $property['type'],
                                $relation['type'],
                                $this::class,
                                $propertyName,
                                ModelCollection::class,
                            )
                        );
                    }
                    $collectionClass = $property['type'];
                }
                if ($id === null) {
                    $this->$propertyName = new $collectionClass();
                    break;
                }
                if ($relation['factoryMethod'] !== null) {
                    $method = $relation['factoryMethod'];
                    $factoryClosure = fn() => $this->$method();
                }
                else {
                    /** @var ManyToMany $attributeClass */
                    $attributeClass = unserialize($relation['instance'], ['allowedClasses' => [ManyToMany::class]]);
                    $connectionQuery = $attributeClass->getConnectionQuery($id, $className, $this);
                    $factoryClosure = fn() => new $collectionClass(
                        $className::query()
                                  ->where('%n IN %sql', $className::getPrimaryKey(), $connectionQuery)
                                  ->cacheTags($this::TABLE.'/'.$this->id.'/relations')
                                  ->get()
                    );
                }

                if ($relation['loadingType'] === LoadingType::LAZY) {
                    $reflection = new ReflectionClass($collectionClass);
                    $this->$propertyName = $reflection->newLazyProxy($factoryClosure);
                    break;
                }
                $this->$propertyName = $factoryClosure();
                break;
        }
    }

    /**
     * Set property value from the database
     *
     * @param  string  $name
     * @param  mixed  $value
     * @param  PropertyConfig|null  $property
     *
     * @return void
     */
    protected function setProperty(string $name, mixed $value, ?array $property = null) : void {
        if (empty($property)) {
            $property = $this::getProperties()[$name] ?? null;
            if (!isset($property)) {
                throw new UndefinedPropertyException('Undefined property '.$this::class.'::$'.$name);
            }
        }

        if (!$property['isBuiltin']) {
            if ($property['isDateTime']) {
                /**
                 * @var class-string<DateTimeInterface> $dateType
                 * @phpstan-ignore varTag.nativeType
                 */
                $dateType = $property['type'] === DateTimeInterface::class ? DateTimeImmutable::class :
                    $property['type'];
                if ($value instanceof DateInterval) {
                    $value = new $dateType($value->format('%H:%i:%s'));
                }
                else if (!($value instanceof DateTimeInterface)) {
                    $valueType = gettype($value);
                    $value = match ($valueType) {
                        'integer', 'string' => new $dateType($value),
                        'NULL'  => $property['allowsNull'] ? null : throw new RuntimeException(
                            sprintf(
                                'Cannot assign type "%s" to a non-nullable DateTime property (%s::%s)',
                                $valueType,
                                $this::class,
                                $name,
                            )
                        ),
                        default => throw new RuntimeException(
                            sprintf(
                                'Cannot assign type "%s" to a DateTime property (%s::%s)',
                                $valueType,
                                $this::class,
                                $name,
                            )
                        ),
                    };
                }
            }
            if ($property['isEnum']) {
                $enum = $property['type'];
                $value = $enum::tryFrom($value);
            }
        }

        if ($value === null && $property['isBuiltin'] && !$property['allowsNull']) {
            switch ($property['type']) {
                case 'int':
                    $value = 0;
                    break;
                case 'string':
                    $value = '';
                    break;
                case 'bool':
                    $value = false;
                    break;
            }
        }

        // Type cast for basic types
        if ($property['isBuiltin']) {
            switch ($property['type']) {
                case 'int':
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'string':
                    $value = (string) $value;
                    break;
                case 'bool':
                    $value = (bool) $value;
                    break;
            }
        }

        $this->$name = $value;
    }

    /**
     * Instantiate properties that have the Instantiate attribute
     *
     * Can instantiate only properties that have an installable class as its type.
     *
     * @return void
     */
    protected function instantiateProperties() : void {
        $properties = $this::getProperties();
        foreach ($properties as $propertyName => $property) {
            // If the property does not have the Instantiate attribute - skip
            // If the property already has a value - skip
            if (!$property['instantiate'] || isset($this->$propertyName)) {
                continue;
            }

            // Check type
            if (!$property['type']) {
                throw new RuntimeException(
                    'Cannot initialize property '.static::class.'::'.$propertyName.' with no type.'
                );
            }
            $className = $property['type'];
            if ($property['isBuiltin'] || !class_exists($className)) {
                // Built in types are not supported - string, int, float,...
                // Non-built in types can also be interfaces or traits, which is invalid. The type needs to be an instantiable class.
                throw new RuntimeException(
                    'Cannot initialize property '.static::class.'::'.$propertyName.' with type '.$property['type'].'.'
                );
            }
            $this->$propertyName = new $className();
        }
    }
}
