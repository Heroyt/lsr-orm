<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Model;
use Mocks\CustomCollection;
use Mocks\CustomInvalidCollection;

#[PrimaryKey('model_f_id')]
class ModelFInvalid extends Model
{
    public const string TABLE = 'modelsF';

    public string $name;

    #[OneToMany(class: ModelG::class)]
    public CustomInvalidCollection $models;

    #[ManyToMany('modelsF_connect', foreignKey: 'id_2', localKey: 'id_1', class: ModelFInvalid::class)]
    public CustomCollection $manyToMany;
}
