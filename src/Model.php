<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Orm;

use ArrayAccess;
use BackedEnum;
use Dibi\Exception;
use Dibi\Row;
use Error;
use JsonSerializable;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Lsr\ObjectValidation\Validator;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Config\ModelConfigProvider;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use ReflectionProperty;

/**
 * @implements ArrayAccess<string, mixed>
 * @phpstan-consistent-constructor
 * @phpstan-import-type RelationConfig from ModelConfig
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
abstract class Model implements JsonSerializable, ArrayAccess
{
    use ModelConfigProvider;
    use ModelFetch;

    /** @var non-empty-string Database table name */
    public const string TABLE = 'models';
    /** @var non-empty-string[] Static tags to add to all cache records for this model. */
    public const    array CACHE_TAGS = [];
    protected const array JSON_EXCLUDE_PROPERTIES = ['row', 'cacheTags', 'logger', 'relationIds'];

    #[NoDB]
    public ?int $id = null;
    protected ?Row $row = null;
    protected Logger $logger;

    /** @var non-empty-string[] Dynamic tags to add to cache records for this model instance */
    protected array $cacheTags = [];

    /** @var array<string, int> */
    protected array $relationIds = [];

    /**
     * @param  int|null  $id  DB model ID
     * @param  Row|null  $dbRow  Prefetched database row
     *
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function __construct(?int $id = null, ?Row $dbRow = null) {
        $pk = $this::getPrimaryKey();
        if (isset($dbRow->$pk) && !isset($id)) {
            assert(is_int($dbRow->$pk));
            $id = $dbRow->$pk;
        }
        if (isset($id) && !empty($this::TABLE)) {
            $this->id = $id;
            ModelRepository::setInstance($this);
            $this->row = $dbRow;
            $this->fetch();
        } else if (isset($dbRow)) {
            $this->row = $dbRow;
            $this->fillFromRow();
        }
        $this->instantiateProperties();
    }

    /**
     * Checks if a model with given ID exists in database
     *
     * @param  int  $id
     *
     * @return bool
     */
    public static function exists(int $id): bool {
        return DB::select(static::TABLE, '*')
                 ->where('%n = %i', static::getPrimaryKey(), $id)
                 ->exists();
    }

    /**
     * Get all models
     *
     * @return static[]
     * @throws ValidationException
     */
    public static function getAll(): array {
        return static::query()->get();
    }

    /**
     * Get one instance of the model by its ID
     *
     * @param  int  $id
     * @param  Row|null  $row
     *
     * @return static
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public static function get(int $id, ?Row $row = null): static {
        return ModelRepository::getInstance(static::class, $id) ?? new static($id, $row);
    }

    /**
     * Start to query the model
     *
     * @return ModelQuery<static>
     */
    public static function query(): ModelQuery {
        return new ModelQuery(static::class);
    }

    /**
     * Clear instance cache
     *
     * @return void
     * @deprecated Use Lsr\Orm\ModelRepository::clearInstances()
     */
    public static function clearInstances(): void {
        ModelRepository::clearInstances(static::class);
    }

    /**
     * Save the model into a database
     *
     * @return bool
     * @throws ValidationException
     */
    public function save(): bool {
        $this->validate();
        return isset($this->id) ? $this->update() : $this->insert();
    }

    /**
     * Validate the model's value
     *
     * @return void
     * @throws \Lsr\ObjectValidation\Exceptions\ValidationException
     */
    public function validate(): void {
        new Validator()->validateAll($this);
    }

    /**
     * Update model in the DB
     *
     * @return bool If the update was successful
     * @throws ValidationException
     */
    public function update(): bool {
        if (!isset($this->id)) {
            return false;
        }
        $this->getLogger()->info('Updating model - ' . $this->id);
        foreach ($this::getBeforeUpdate() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        try {
            DB::update($this::TABLE, $this->getQueryData(), ['%n = %i', $this::getPrimaryKey(), $this->id]);
        } catch (Exception $e) {
            $this->getLogger()->error('Error running update query: ' . $e->getMessage());
            $this->getLogger()->debug('Query: ' . $e->getSql());
            $this->getLogger()->exception($e);
            return false;
        }
        foreach ($this::getAfterDelete() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        return true;
    }

    /**
     * Get logger for this model type
     *
     * @return Logger
     */
    public function getLogger(): Logger {
        if (!isset($this->logger)) {
            $this->logger = ModelRepository::getLogger(static::class);
        }
        return $this->logger;
    }

    /**
     * Get an array of values for DB to insert/update. Values are validated.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function getQueryData(): array {
        $data = [];

        foreach ($this::getProperties() as $propertyName => $property) {
            if (
                $property['noDb']
                || ($property['isVirtual'] ?? false)
                || (!isset($this->$propertyName) && $property['isPrimaryKey'])
            ) {
                continue;
            }

            // Handle relations
            if ($property['relation'] !== null) {
                /** @var RelationConfig $relation */
                $relation = $property['relation'];

                // Do not include lazy-loaded fields that have not been set yet
                $reflection = new \ReflectionProperty($this, $propertyName);
                try {
                    if (!$reflection->isInitialized($this)) {
                        continue;
                    }
                } catch (Error $e) {
                    if (str_contains($e->getMessage(), 'must not be accessed before initialization')) {
                        continue;
                    }
                    throw $e;
                }

                switch ($relation['type']) {
                    case OneToOne::class:
                    case ManyToOne::class:
                        assert($this->$propertyName === null || $this->$propertyName instanceof self);
                        $data[empty($relation['localKey']) ? $relation['foreignKey'] :
                            $relation['localKey']] = $this->$propertyName?->id;
                        break;
                }
                continue;
            }

            // Handle insert-extend mapping
            if ($property['isExtend']) {
                assert($this->$propertyName instanceof InsertExtendInterface);
                $this->$propertyName->addQueryData($data);
                continue;
            }

            $columnName = Strings::toSnakeCase($propertyName);

            // Handle enum values
            if ($property['isEnum']) {
                assert($this->$propertyName instanceof BackedEnum);
                $data[$columnName] = $this->$propertyName->value;
                continue;
            }

            // Check type
            if (in_array($property['type'], ['array', 'object'], true)) {
                continue;
            }

            $data[$columnName] = $this->$propertyName ?? null;
        }
        return $data;
    }

    /**
     * Insert a new model into the DB
     *
     * @return bool
     * @throws ValidationException
     */
    public function insert(): bool {
        $this->getLogger()->info('Inserting new model');
        foreach ($this::getBeforeInsert() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        try {
            DB::insert($this::TABLE, $this->getQueryData());
            $this->id = DB::getInsertId();
        } catch (Exception $e) {
            $this->getLogger()->error('Error running insert query: ' . $e->getMessage());
            $this->getLogger()->debug('Query: ' . $e->getSql());
            $this->getLogger()->exception($e);
            return false;
        }
        if (empty($this->id)) {
            $this->getLogger()->error('Insert query passed, but ID was not returned.');
            return false;
        }
        ModelRepository::setInstance($this);

        foreach ($this::getAfterInsert() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        return true;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array<string, mixed> data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): array {
        $data = [];
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            if (
                in_array($propertyName, $this::JSON_EXCLUDE_PROPERTIES, true)
                || !empty($property->getAttributes(JsonExclude::class))
            ) {
                continue;
            }
            $data[$propertyName] = $property->getValue($this);
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset): mixed {
        if ($this->offsetExists($offset)) {
            return $this->$offset;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool {
        return property_exists($this, $offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void {
        if (isset($offset) && $this->offsetExists($offset)) {
            $this->$offset = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void {
        // Do nothing
    }

    /**
     * Delete model from DB
     *
     * @return bool
     */
    public function delete(): bool {
        if (!isset($this->id)) {
            return false;
        }
        $this->getLogger()->info('Delete model: ' . $this::TABLE . ' of ID: ' . $this->id);

        foreach ($this::getBeforeDelete() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }

        try {
            DB::delete($this::TABLE, ['%n = %i', $this::getPrimaryKey(), $this->id]);
            ModelRepository::removeInstance($this);
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());
            return false;
        }

        foreach ($this::getAfterDelete() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }

        return true;
    }

    /**
     * @return non-empty-string[]
     */
    protected function getCacheTags(): array {
        return array_merge(
            ['models', $this::TABLE, $this::TABLE . '/' . $this->id],
            $this::CACHE_TAGS,
            $this->cacheTags,
        );
    }
}
