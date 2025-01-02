<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('model_e_id')]
class ModelE extends Model
{
    public const string TABLE = 'modelsE';

    public string $name;

    /** @var ModelCollection<ModelD> */
    #[ManyToMany('modelsD_modelsE', class: ModelD::class, loadingType: LoadingType::EAGER)]
    public ModelCollection $models;
}
