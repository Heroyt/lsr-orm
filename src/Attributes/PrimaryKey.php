<?php

declare(strict_types=1);

namespace Lsr\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class PrimaryKey
{
    public function __construct(
        public string $column,
    ) {
    }
}
