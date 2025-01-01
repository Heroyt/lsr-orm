<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_model_c')]
class ModelCC extends Model
{
    public const string TABLE = 'models_c';

    public string $name;

    #[ManyToOne]
    public ModelCB $parent;

    /** @var ModelCollection<ModelCA>  */
    #[OneToMany(class: ModelCA::class)]
    public ModelCollection $children;
}
