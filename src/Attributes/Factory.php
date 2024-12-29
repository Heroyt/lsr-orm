<?php

namespace Lsr\Orm\Attributes;

use Attribute;
use Lsr\Orm\Interfaces\FactoryInterface;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_CLASS)]
class Factory
{

    /**
     * @param  class-string<FactoryInterface<Model>>  $factoryClass
     * @param  array<string, mixed>  $defaultOptions
     */
    public function __construct(
      public string $factoryClass,
      public array  $defaultOptions = [],
    ) {}

}