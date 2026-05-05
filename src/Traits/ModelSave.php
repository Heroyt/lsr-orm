<?php
declare(strict_types=1);

namespace Lsr\Orm\Traits;

use BackedEnum;
use Dibi\Drivers\PdoDriver;
use Dibi\Exception;
use Error;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelRepository;
use ReflectionException;
use ReflectionProperty;

/**
 * @phpstan-import-type RelationConfig from ModelConfig
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
trait ModelSave
{
    /**
     * @param array<string, mixed> $queryData
     * @return array<int, array{column: string, value: mixed, type: int}>
     */
    private function normalizeNativeQueryData(array $queryData): array
    {
        $normalized = [];
        foreach ($queryData as $key => $value) {
            $parts = explode('%', $key, 2);
            $column = $parts[0];
            $modifier = $parts[1] ?? null;

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $value = (int)$value;
            }

            $type = match (true) {
                $modifier === 'bin' => \PDO::PARAM_LOB,
                is_int($value) => \PDO::PARAM_INT,
                $value === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            };

            $normalized[] = [
                'column' => $column,
                'value' => $value,
                'type' => $type,
            ];
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $queryData
     */
    private function tryNativePdoInsert(array $queryData): bool
    {
        $driver = DB::getConnection()->connection->getDriver();
        if (!$driver instanceof PdoDriver) {
            return false;
        }

        $pdo = $driver->getResource();
        if (!$pdo instanceof \PDO) {
            return false;
        }

        $normalized = $this->normalizeNativeQueryData($queryData);
        $columns = array_map(static fn(array $item): string => $item['column'], $normalized);
        $quotedColumns = array_map(
            static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        );
        $placeholders = array_map(
            static fn(string $column): string => ':' . $column,
            $columns
        );
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '``', $this::TABLE),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }
        foreach ($normalized as $item) {
            $statement->bindValue(':' . $item['column'], $item['value'], $item['type']);
        }
        if (!$statement->execute()) {
            return false;
        }
        $this->id = (int)$pdo->lastInsertId() ?: null;
        return $this->id !== null;
    }

    /**
     * @param array<string, mixed> $queryData
     */
    private function tryNativePdoUpdate(array $queryData): bool
    {
        $driver = DB::getConnection()->connection->getDriver();
        if (!$driver instanceof PdoDriver) {
            return false;
        }

        $pdo = $driver->getResource();
        if (!$pdo instanceof \PDO) {
            return false;
        }

        $normalized = $this->normalizeNativeQueryData($queryData);
        if ($normalized === []) {
            return true;
        }

        $assignments = [];
        foreach ($normalized as $item) {
            $assignments[] = '`' . str_replace('`', '``', $item['column']) . '` = :' . $item['column'];
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :_primary_id',
            str_replace('`', '``', $this::TABLE),
            implode(', ', $assignments),
            str_replace('`', '``', $this::getPrimaryKey())
        );
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }
        foreach ($normalized as $item) {
            $statement->bindValue(':' . $item['column'], $item['value'], $item['type']);
        }
        $statement->bindValue(':_primary_id', $this->id, \PDO::PARAM_INT);
        return $statement->execute();
    }

    /**
     * @template T of Model
     * @param class-string<Model> $relationClass
     * @param ModelCollection<T> $model
     */
    private function resolveOneToManyRelationClass(
        string          $propertyName,
        string          $relationClass,
        ModelCollection $model
    ): string
    {
        if ($relationClass::TABLE !== Model::TABLE) {
            return $relationClass;
        }

        foreach ($model as $relatedModel) {
            if ($relatedModel instanceof Model
                && $relatedModel::TABLE !== Model::TABLE
            ) {
                return $relatedModel::class;
            }
        }

        $classProperty = preg_replace('/s$/', '', $propertyName) . 'Class';
        if (property_exists($this, $classProperty)
            && is_string($this->$classProperty)
            && is_subclass_of($this->$classProperty, Model::class)
            && $this->$classProperty::TABLE !== Model::TABLE
        ) {
            /** @var class-string<Model> $resolvedClass */
            $resolvedClass = $this->$classProperty;
            return $resolvedClass;
        }

        return $relationClass;
    }
    /**
     * Save the model into a database
     *
     * @return bool
     * @throws ValidationException
     * @phpstan-assert-if-true !null $this->id
     */
    public function save() : bool {
        $this->validate();
        DB::begin();
        if ($this->isLoaded() ? $this->update() : $this->insert()) {
            DB::commit();
            return true;
        }
        DB::rollback();
        return false;
    }

    /**
     * Update model in the DB
     *
     * @return bool If the update was successful
     * @throws ValidationException
     * @phpstan-assert-if-true !null $this->id
     */
    public function update() : bool {
        if (!$this->isLoaded()) {
            return false;
        }
        $this->getLogger()->info('Updating model - '.$this->id);
        foreach ($this::getBeforeUpdate() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        $queryData = $this->getQueryData();

        if (!empty($queryData)) {
            // Update only if there are any changes
            try {
                if (!$this->tryNativePdoUpdate($queryData)) {
                    DB::update($this::TABLE, $queryData, ['%n = %i', $this::getPrimaryKey(), $this->id]);
                }
            } catch (Exception $e) {
                $this->getLogger()->error('Error running update query: '.$e->getMessage());
                $this->getLogger()->debug('Query: '.$e->getSql());
                $this->getLogger()->exception($e);
                return false;
            }
        }

        if (!$this->updateOneToManyRelations()) {
            return false;
        }

        if (!$this->updateManyToManyRelations()) {
            return false;
        }

        // Update the model's original values
        foreach ($this->getChangedProperties() as $key => $value) {
            if ($value instanceof Model) {
                $value = $value->id;
            }
            else if ($value instanceof ModelCollection) {
                $value = $value->map(fn(Model $m) => $m->id);
            }
            else if ($value instanceof InsertExtendInterface) {
                $data = [];
                $value->addQueryData($data);
                $value = $data;
            }
            $this->originalValues[$key] = $value;
        }

        foreach ($this::getAfterUpdate() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        return true;
    }

    /**
     * Get an array of changed properties
     * @return array<non-empty-string, mixed> Property name-value pairs of changed properties
     * @throws ReflectionException
     */
    public function getChangedProperties() : array {
        $changed = [];
        foreach ($this::getProperties() as $propertyName => $property) {
            if (
                $property['noDb']
                || ($property['isVirtual'] ?? false)
                || (!isset($this->$propertyName) && $property['isPrimaryKey'])
                || !$this->hasChanged($propertyName)
            ) {
                continue;
            }
            $changed[$propertyName] = $this->$propertyName;
        }
        return $changed;
    }

    /**
     * Get an array of values for DB to insert/update. Values are validated.
     *
     * @return array<string, mixed>
     * @throws ValidationException|ReflectionException
     */
    public function getQueryData(bool $filterChanged = true) : array {
        $data = [];

        foreach ($this::getProperties() as $propertyName => $property) {
            if (
                $property['noDb']
                || ($property['isVirtual'] ?? false)
                || (!isset($this->$propertyName) && $property['isPrimaryKey'])
                || ($filterChanged && !$this->hasChanged($propertyName)) // Filter out unchanged properties
            ) {
                continue;
            }

            // Handle relations
            if ($property['relation'] !== null) {
                /** @var RelationConfig $relation */
                $relation = $property['relation'];

                // Do not include lazy-loaded fields that have not been set yet
                $reflection = new ReflectionProperty($this, $propertyName);
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

            // Prepare possible transform function
            $maybeTransformForSave = function (mixed $value) use ($property, $propertyName): mixed {
                // Custom transform after fetching from DB
                if (array_key_exists('hasTransform', $property) && $property['hasTransform']) {
                    $propertyReflection = $this::getReflection()->getProperty($propertyName);
                    $transformAttributes = $propertyReflection->getAttributes(Transform::class, \ReflectionAttribute::IS_INSTANCEOF);
                    foreach ($transformAttributes as $attribute) {
                        /** @var Transform $transformInstance */
                        $transformInstance = $attribute->newInstance();
                        $value = $transformInstance->transformSave($value, $this);
                    }
                }
                return $value;
            };

            $columnName = Strings::toSnakeCase($propertyName);

            // Handle enum values
            if ($property['isEnum']) {
                assert($this->$propertyName instanceof BackedEnum);
                $data[$columnName] = $maybeTransformForSave($this->$propertyName->value);
                continue;
            }

            // Check type
            if (in_array($property['type'], ['array', 'object'], true)) {
                continue;
            }

            $data[$columnName] = $maybeTransformForSave($this->$propertyName ?? null);
        }
        return $data;
    }

    /**
     * @throws ReflectionException
     */
    protected function updateOneToManyRelations(bool $filterChanged = true) : bool {
        foreach ($this::getProperties() as $propertyName => $property) {
            if (
                $property['relation'] === null
                || $property['relation']['type'] !== OneToMany::class
                || ($filterChanged && !$this->hasChanged($propertyName))
            ) {
                continue;
            }

            $reflection = new ReflectionProperty($this, $propertyName);
            if (!$reflection->isInitialized($this)) {
                continue;
            }

            $model = $reflection->getValue($this);
            assert($model instanceof ModelCollection);

            /** @var class-string<Model> $relationClass */
            $relationClass = $this->resolveOneToManyRelationClass(
                $propertyName,
                $property['relation']['class'],
                $model
            );

            // Find the original models' ids
            /** @var int[] $originalIds */
            $originalIds = $this->originalValues[$propertyName] ?? [];
            /** @var int[] $currentIds */
            $currentIds = $model->map(fn(Model $m) => $m->id);

            // TODO: Make sure that the related models are saved
            /** @var int[] $modelsToDelete */
            $modelsToDelete = array_filter(array_diff($originalIds, $currentIds));
            /** @var int[] $modelsToInsert */
            $modelsToInsert = array_filter(array_diff($currentIds, $originalIds));
            $relationPK = $relationClass::getPrimaryKey();

            if (!empty($modelsToDelete)) {
                // Unset the foreign key in the relation table
                try {
                    DB::update(
                        $relationClass::TABLE,
                        [
                            $property['relation']['foreignKey'] => null,
                        ],
                        [
                            '%n IN %in',
                            $relationPK,
                            $modelsToDelete,
                        ],
                    );
                } catch (Exception $e) {
                    $this->getLogger()->error('Error updating one-to-many relation: '.$e->getMessage());
                    $this->getLogger()->debug('Query: '.$e->getSql());
                    $this->getLogger()->exception($e);
                    return false;
                }
            }

            if (!empty($modelsToInsert)) {
                // Set the foreign key in the relation table
                try {
                    DB::update(
                        $relationClass::TABLE,
                        [
                            $property['relation']['foreignKey'] => $this->id,
                        ],
                        [
                            '%n IN %in',
                            $relationPK,
                            $modelsToInsert,
                        ],
                    );
                } catch (Exception $e) {
                    $this->getLogger()->error('Error updating one-to-many relation: '.$e->getMessage());
                    $this->getLogger()->debug('Query: '.$e->getSql());
                    $this->getLogger()->exception($e);
                    return false;
                }
            }

            // Update original Ids
            $this->originalValues[$propertyName] = $currentIds;

            // Call external hooks on updated models
            foreach ($relationClass::getAfterExternalUpdate() as $function) {
                foreach ($modelsToInsert as $id) {
                    $function($id);
                }
                foreach ($modelsToDelete as $id) {
                    $function($id);
                }
            }
        }
        return true;
    }

    /**
     * @throws ReflectionException
     */
    protected function updateManyToManyRelations(bool $filterChanged = true) : bool {
        foreach ($this::getProperties() as $propertyName => $property) {
            if (
                $property['relation'] === null
                || $property['relation']['type'] !== ManyToMany::class
                || ($filterChanged && !$this->hasChanged($propertyName))
            ) {
                continue;
            }

            $relation = unserialize($property['relation']['instance'], ['allowed_classes' => [ManyToMany::class]]);
            assert($relation instanceof ManyToMany);

            /** @var class-string<Model> $relationClass */
            $relationClass = $property['relation']['class'];

            $reflection = new ReflectionProperty($this, $propertyName);
            if (!$reflection->isInitialized($this)) {
                continue;
            }

            $model = $reflection->getValue($this);
            assert($model instanceof ModelCollection);

            // Find the original models' ids
            /** @var int[] $originalIds */
            $originalIds = $this->originalValues[$propertyName] ?? [];

            /** @var int[] $currentIds */
            $currentIds = $model->map(fn(Model $m) => $m->id);

            // TODO: Make sure that the related models are saved
            /** @var int[] $modelsToDelete */
            $modelsToDelete = array_filter(array_diff($originalIds, $currentIds));
            /** @var int[] $modelsToInsert */
            $modelsToInsert = array_filter(array_diff($currentIds, $originalIds));

            $thisPK = $this::getPrimaryKey();
            $relationPK = $relationClass::getPrimaryKey();
            $table = $relation->getThroughTableName($relationClass, $this);

            if (!empty($modelsToDelete)) {
                // Unset the foreign key in the relation table
                try {
                    DB::delete(
                        $table,
                        [
                            '%n = %i AND %n IN %in',
                            $thisPK,
                            $this->id,
                            $relationPK,
                            $modelsToDelete,
                        ],
                    );
                } catch (Exception $e) {
                    $this->getLogger()->error('Error updating many-to-many relation: '.$e->getMessage());
                    $this->getLogger()->debug('Query: '.$e->getSql());
                    $this->getLogger()->exception($e);
                    return false;
                }
            }

            if (!empty($modelsToInsert)) {
                // Set the foreign key in the relation table
                try {
                    foreach ($modelsToInsert as $id) {
                        DB::insertIgnore(
                            $table,
                            [
                                $thisPK     => $this->id,
                                $relationPK => $id,
                            ]
                        );
                    }
                } catch (Exception $e) {
                    $this->getLogger()->error('Error updating many-to-many relation: '.$e->getMessage());
                    $this->getLogger()->debug('Query: '.$e->getSql());
                    $this->getLogger()->exception($e);
                    return false;
                }
            }

            // Update original Ids
            $this->originalValues[$propertyName] = $currentIds;

            // Call external hooks on updated models
            foreach ($relationClass::getAfterExternalUpdate() as $function) {
                foreach ($modelsToInsert as $id) {
                    $function($id);
                }
                foreach ($modelsToDelete as $id) {
                    $function($id);
                }
            }
        }
        return true;
    }

    /**
     * Delete model from DB
     *
     * @return bool
     */
    public function delete() : bool {
        if (!$this->isLoaded()) {
            return false;
        }
        $this->getLogger()->info('Delete model: '.$this::TABLE.' of ID: '.$this->id);

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
     * Insert a new model into the DB
     *
     * @return bool
     * @throws ValidationException
     * @throws ReflectionException
     * @phpstan-assert-if-true !null $this->id
     */
    public function insert() : bool {
        if ($this->isLoaded()) {
            return false;
        }
        $this->getLogger()->info('Inserting new model');
        foreach ($this::getBeforeInsert() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }

        $queryData = $this->getQueryData(false);

        try {
            if (!$this->tryNativePdoInsert($queryData)) {
                DB::insert($this::TABLE, $queryData);
                $this->id = DB::getInsertId();
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Error running insert query: '.$e->getMessage());
            $this->getLogger()->debug('Query: '.$e->getSql());
            $this->getLogger()->exception($e);
            return false;
        }
        if (empty($this->id)) {
            $this->getLogger()->error('Insert query passed, but ID was not returned.');
            return false;
        }
        ModelRepository::setInstance($this);

        if (!$this->updateOneToManyRelations(false)) {
            return false;
        }

        if (!$this->updateManyToManyRelations(false)) {
            return false;
        }

        // Update the model's original values
        foreach ($queryData as $key => $value) {
            $this->originalValues[$key] = $value;
        }

        foreach ($this::getAfterInsert() as $method) {
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        return true;
    }

}
