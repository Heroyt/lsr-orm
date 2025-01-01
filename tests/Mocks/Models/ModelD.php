<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('model_d_id')]
class ModelD extends Model
{
    public const string TABLE = 'modelsD';

    public string $name;

    /** @var ModelCollection<ModelE> */
    #[ManyToMany('modelsD_modelsE', class: ModelE::class)]
    public ModelCollection $models;
}
