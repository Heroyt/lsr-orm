<?php

declare(strict_types=1);

namespace Lsr\Orm;

enum LoadingType
{
    case EAGER;
    case LAZY;
}
