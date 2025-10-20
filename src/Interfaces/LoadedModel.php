<?php
declare(strict_types=1);

namespace Lsr\Orm\Interfaces;

use Lsr\Orm\Model;

/**
 * Marker interface for models that have been loaded from the database.
 *
 * @phpstan-require-extends Model
 * @property int $id Primary key property
 */
interface LoadedModel
{

}