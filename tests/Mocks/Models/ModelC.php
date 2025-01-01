<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('model_c_id')]
class ModelC extends Model
{
    public const string TABLE = 'modelsC';

    public string $value0;
    public SimpleData $data;
}
