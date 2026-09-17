<?php

declare(strict_types=1);

namespace Mocks\Types;

use Attribute;
use Lsr\Orm\Attributes\ColumnType;
use Lsr\Orm\Model;

/**
 * Stores an array in a text column as JSON.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class JsonType extends ColumnType
{
    public function toDatabase(mixed $value, Model $model): mixed {
        if ($value === null) {
            return null;
        }
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function fromDatabase(mixed $value, Model $model): mixed {
        if ( ! is_string($value) || $value === '') {
            return null;
        }
        /** @var array<mixed>|null $decoded */
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
