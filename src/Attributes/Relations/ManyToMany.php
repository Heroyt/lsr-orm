<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Relations;

use Attribute;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ManyToMany extends ModelRelation
{
    use WithType;

    /**
     * @param  string  $through
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @param  class-string<Model>|null  $class
     * @param  LoadingType  $loadingType
     * @param  null|non-empty-string  $factoryMethod
     */
    public function __construct(
        public string      $through = '',
        public string      $foreignKey = '',
        public string      $localKey = '',
        public ?string     $class = null,
        public LoadingType $loadingType = LoadingType::LAZY,
        public ?string $factoryMethod = null,
    ) {
    }

    /**
     * Get a query that returns model ids
     *
     * @param  int  $id
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     *
     * @return Fluent
     */
    public function getConnectionQuery(int $id, string | Model $targetClass, string | Model $class) : Fluent {
        return DB::select(
            $this->getThroughTableName($targetClass, $class),
            '%n as %n',
            $this->getForeignKey($targetClass, $class),
            $targetClass::getPrimaryKey(),
        )
                 ->where('%n = %i', $this->getLocalKey($targetClass, $class), $id);
    }

    /**
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     * @return string
     */
    public function getThroughTableName(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->through)) {
            /** @var non-empty-string $table */
            $table = $class::TABLE;
            /** @var non-empty-string $targetTable */
            $targetTable = $targetClass::TABLE;

            // Alphabetically sort the table names to ensure consistent naming
            if (strcmp($table, $targetTable) > 0) {
                // Swap values
                [$table, $targetTable] = [$targetTable, $table];
            }

            $this->through = '::'.$table.'_'.$targetTable;
        }
        return $this->through;
    }

    /**
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     *
     * @return string
     */
    public function getForeignKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->foreignKey)) {
            $this->foreignKey = $targetClass::getPrimaryKey();
        }
        return $this->foreignKey;
    }

    /**
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     *
     * @return string
     */
    public function getLocalKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->localKey)) {
            $this->localKey = $class::getPrimaryKey();
        }
        return $this->localKey;
    }

    /**
     * @param  Model[]  $targetClasses
     * @param  Model  $class
     *
     * @return Fluent
     */
    public function getInsertQuery(array $targetClasses, Model $class) : Fluent {
        $data = [];
        foreach ($targetClasses as $targetClass) {
            $data[] = [
                $this->getLocalKey($targetClass, $class)   => $class->id,
                $this->getForeignKey($targetClass, $class) => $targetClass->id,
            ];
        }
        return DB::insertGet(
               $this->through,
            ...$data
        );
    }
}
