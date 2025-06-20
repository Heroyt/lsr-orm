<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class SerializationAlias
{

    public function __construct(
        public string $alias,
    ) {}

}