<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('id_model_a')]
class ModelCA extends Model
{
    public const string TABLE = 'models_a';

    public string $name;

    #[ManyToOne]
    public ModelCB $parent;

    #[ManyToOne]
    public ModelCC $parentC;
}
