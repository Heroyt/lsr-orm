<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('model_b_id')]
class ModelBLazy extends Model
{
    public const string TABLE = 'modelsB';

    public string $description;
    public TestEnum $modelType;

    #[ManyToOne]
    public ?ModelA $parent = null;
}
