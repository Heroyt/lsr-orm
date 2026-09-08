<?php

declare(strict_types=1);

namespace Lsr\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class NoDB
{
}
