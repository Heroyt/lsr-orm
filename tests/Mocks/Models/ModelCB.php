<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_model_b')]
class ModelCB extends Model
{
    public const string TABLE = 'models_b';

    public string $name;

    /** @var ModelCollection<ModelCA>  */
    #[OneToMany(class: ModelCA::class)]
    public ModelCollection $children;

    /** @var ModelCollection<ModelCC>  */
    #[OneToMany(class: ModelCC::class, loadingType: LoadingType::EAGER)]
    public ModelCollection $childrenC;
}
