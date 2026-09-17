<?php

declare(strict_types=1);

namespace Mocks\Types;

use Attribute;
use Dibi\Expression;
use Lsr\Orm\Attributes\ColumnType;
use Lsr\Orm\Model;

/**
 * Converts the value in SQL on write, the way a spatial or encrypted column does.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class UpperCaseType extends ColumnType
{
    public function toDatabase(mixed $value, Model $model): mixed {
        if ($value === null) {
            return null;
        }
        assert(is_string($value));
        return new Expression('upper(%s)', $value);
    }

    public function fromDatabase(mixed $value, Model $model): mixed {
        return is_string($value) ? $value : null;
    }
}
