<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;

#[PrimaryKey('model_b_id')]
class ModelB extends Model
{
    public const string TABLE = 'modelsB';

    public string $description;
    public TestEnum $modelType;

    #[ManyToOne(loadingType: LoadingType::EAGER)]
    public ?ModelA $parent = null;
}
