<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

#[PrimaryKey('model_g_id')]
class ModelG extends Model
{
    public const string TABLE = 'modelsG';

    public string $name;

    #[ManyToOne]
    public ModelF $parent;
}
