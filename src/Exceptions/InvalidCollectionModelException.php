<?php

declare(strict_types=1);

namespace Lsr\Orm\Exceptions;

use InvalidArgumentException;

class InvalidCollectionModelException extends InvalidArgumentException
{
    public const int INVALID_MODEL_TYPE_CODE = 1;
    public const int UNINITIALIZED_MODEL_CODE = 2;
}
