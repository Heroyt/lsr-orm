<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Model;

class ModelWithNullableEnum extends Model
{
    public const string TABLE = 'model_with_nullable_enum';

    public ?TestEnum $nullableType = null;
}
