<?php

declare(strict_types=1);

namespace Lsr\Orm\Attributes;

use Dibi\Expression;
use Dibi\Literal;
use Lsr\Orm\Model;

/**
 * Describes how one property is converted to and from its database column.
 *
 * A column type is a reusable, stateless description of a storage format - the same type class can
 * be used on any number of properties and models. Extend this class, mark the concrete child class
 * with `#[Attribute(Attribute::TARGET_PROPERTY)]` and place it on a model property:
 *
 * ```php
 * #[Attribute(Attribute::TARGET_PROPERTY)]
 * readonly class PointType extends ColumnType
 * {
 *     public function __construct(public int $srid = 4326) {}
 *
 *     public function toDatabase(mixed $value, Model $model) : mixed {
 *         assert($value === null || $value instanceof Point);
 *         return $value === null
 *             ? null
 *             : new Expression('ST_GeomFromText(%s, %i)', $value->toWkt(), $this->srid);
 *     }
 *
 *     public function fromDatabase(mixed $value, Model $model) : mixed {
 *         return is_string($value) ? Point::fromWkt($value) : null;
 *     }
 * }
 * ```
 *
 * A property may declare at most one column type. When present, the type fully owns the conversion
 * of that column - the built-in enum, date-time, scalar-cast and array/object handling is skipped,
 * so a typed property may hold any PHP value. {@see Transform} attributes still run, always on the
 * PHP-side value: on save before {@see ColumnType::toDatabase()}, on load after
 * {@see ColumnType::fromDatabase()}.
 */
abstract readonly class ColumnType
{
    /**
     * Convert the property value into the value written to the database.
     *
     * Return `null` or a scalar to have the value bound as a query parameter. Return a dibi
     * {@see Expression} (or {@see Literal}) to convert the value in SQL instead - for example
     * `new Expression('ST_GeomFromText(%s, %i)', $wkt, 4326)` for a spatial column or
     * `new Expression('%bin', $binary)` for a binary one. Expressions are always written through
     * dibi; they disable the native PDO fast path for the whole statement.
     */
    abstract public function toDatabase(mixed $value, Model $model): mixed;

    /**
     * Convert a raw database value into the value assigned to the property.
     *
     * The value is the column as returned by the driver, including `null`, and the result must be
     * assignable to the property's declared type.
     */
    abstract public function fromDatabase(mixed $value, Model $model): mixed;
}
