<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('id_model')]
class ModelInvalidInstantiate extends Model
{
    public const string TABLE = 'model_invalid_instantiate';

    #[Instantiate]
    public $val; // @phpstan-ignore missingType.property
}
