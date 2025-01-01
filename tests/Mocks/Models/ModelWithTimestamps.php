<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;
use Lsr\Orm\ModelTraits\WithCreatedAt;
use Lsr\Orm\ModelTraits\WithUpdatedAt;

#[PrimaryKey('id_with_timestamps')]
class ModelWithTimestamps extends Model
{
    use WithUpdatedAt;
    use WithCreatedAt;

    public const string TABLE = 'with_timestamps';

    public string $name;
}
