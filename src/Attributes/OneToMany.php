<?php

namespace Lsr\Orm\Attributes;

use Attribute;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class OneToMany extends ModelRelation
{
    use WithType;

    public function __construct(
      public string      $foreignKey = '',
      public string      $localKey = '',
      public ?string     $class = null,
      public LoadingType $loadingType = LoadingType::EAGER,
    ) {}

    /**
     * @param  string|Model  $targetClass
     * @param  string|Model  $class
     *
     * @return string
     */
    public function getLocalKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->localKey)) {
            $this->localKey = $this->getForeignKey($targetClass, $class);
        }
        return $this->localKey;
    }

    /**
     * @param  string|Model  $targetClass
     * @param  string|Model  $class
     *
     * @return string
     */
    public function getForeignKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->foreignKey)) {
            $this->foreignKey = $class::getPrimaryKey();
        }
        return $this->foreignKey;
    }

}