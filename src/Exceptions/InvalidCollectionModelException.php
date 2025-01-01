<?php

declare(strict_types=1);

namespace Lsr\Orm\Exceptions;

class InvalidCollectionModelException extends \InvalidArgumentException
{
    public const int INVALID_MODEL_TYPE_CODE = 1;
    public const int UNINITIALIZED_MODEL_CODE = 2;
}
