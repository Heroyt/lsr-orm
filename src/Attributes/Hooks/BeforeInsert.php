<?php

declare(strict_types=1);

namespace Lsr\Orm\Attributes\Hooks;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class BeforeInsert
{
}
