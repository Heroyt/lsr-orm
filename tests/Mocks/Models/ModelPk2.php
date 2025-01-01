<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Model;

class ModelPk2 extends Model
{
    public const string TABLE = 'invalid';

    public int $modelPk2Id;
}
