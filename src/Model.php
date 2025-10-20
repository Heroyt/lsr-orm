<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Orm;

use ArrayAccess;
use Deprecated;
use Dibi\Exception;
use Dibi\Row;
use JsonSerializable;
use Lsr\Db\DB;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Validator;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\Config\ModelConfigProvider;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Interfaces\LoadedModel;
use Lsr\Orm\Traits\Cacheable;
use Lsr\Orm\Traits\ModelFetch;
use Lsr\Orm\Traits\ModelSave;
use Lsr\Orm\Traits\WithArrayAccess;
use Lsr\Orm\Traits\WithLogger;
use Lsr\Orm\Traits\WithSerialization;

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
    use ModelSave;
    use WithArrayAccess;
    use Cacheable;
    use WithSerialization;
    use WithLogger;

    /** @var non-empty-string Database table name */
    public const string TABLE = 'models';

    #[NoDB]
    public ?int $id = null;
    #[JsonExclude]
    protected ?Row $row = null;

    /** @var array<string, int> */
    #[JsonExclude]
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
        }
        else if (isset($dbRow)) {
            $this->row = $dbRow;
            $this->fillFromRow();
        }
        $this->instantiateProperties();
    }

    /**
     * Check if the model is attached (loaded) to a database row.
     *
     * @return bool
     * @phpstan-assert-if-true !null $this->id
     */
    public function isLoaded(): bool
    {
        return $this->id !== null;
    }

    /**
     * Checks if a model with given ID exists in database
     *
     * @throws Exception
     */
    public static function exists(int $id, bool $cache = true) : bool {
        return DB::select(static::TABLE, '*')
                 ->where('%n = %i', static::getPrimaryKey(), $id)
            ->exists($cache);
    }

    /**
     * Get all models
     *
     * @return (static&LoadedModel)[]
     * @throws ValidationException
     */
    public static function getAll() : array {
        return static::query()->get();
    }

    /**
     * Get one instance of the model by its ID
     *
     * @param  int  $id
     * @param  Row|null  $row
     *
     * @return static&LoadedModel
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public static function get(int $id, ?Row $row = null) : static {
        /** @phpstan-ignore return.type */
        return ModelRepository::getInstance(static::class, $id) ?? new static($id, $row);
    }

    /**
     * Start to query the model
     *
     * @return ModelQuery<static>
     */
    public static function query() : ModelQuery {
        return new ModelQuery(static::class);
    }

    /**
     * Clear instance cache
     */
    #[Deprecated('Use Lsr\Orm\ModelRepository::clearInstances()')]
    public static function clearInstances() : void {
        ModelRepository::clearInstances(static::class);
    }

    /**
     * Validate the model's value
     *
     * @return void
     * @throws \Lsr\ObjectValidation\Exceptions\ValidationException
     */
    public function validate() : void {
        new Validator()->validateAll($this);
    }

}
