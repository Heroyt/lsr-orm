<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

/**
 * Holds a raw dibi expression in an untyped column, the way applications pass spatial values.
 */
#[PrimaryKey('id_model')]
class ModelWithExpression extends Model
{
    public const string TABLE = 'model_with_expression';

    public string $name = '';

    public mixed $value = null;
}
