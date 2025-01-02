<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Relations;

use Attribute;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ManyToOne extends ModelRelation
{
    use WithType;

    /**
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @param  class-string<Model>|null  $class
     * @param  LoadingType  $loadingType
     * @param  null|non-empty-string  $factoryMethod
     */
    public function __construct(
        public string      $foreignKey = '',
        public string      $localKey = '',
        public ?string     $class = null,
        public LoadingType $loadingType = LoadingType::LAZY,
        public ?string $factoryMethod = null,
    ) {
    }

    /**
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     *
     * @return string
     */
    public function getLocalKey(string | Model $targetClass, string | Model $class): string {
        if (empty($this->localKey)) {
            $this->localKey = $this->getForeignKey($targetClass, $class);
        }
        return $this->localKey;
    }

    /**
     * @param  class-string<Model>|Model  $targetClass
     * @param  class-string<Model>|Model  $class
     *
     * @return string
     */
    public function getForeignKey(string | Model $targetClass, string | Model $class): string {
        if (empty($this->foreignKey)) {
            $this->foreignKey = $targetClass::getPrimaryKey();
        }
        return $this->foreignKey;
    }
}
