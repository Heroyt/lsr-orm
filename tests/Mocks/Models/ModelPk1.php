<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Model;

class ModelPk1 extends Model
{
    public const string TABLE = 'invalid';

    public int $idModelPk1;
}
