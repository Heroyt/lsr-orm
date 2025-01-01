<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Model;

class ModelInvalid extends Model
{
    public const string TABLE = 'invalid';

    public string $column1;
    public string $column2;
}
