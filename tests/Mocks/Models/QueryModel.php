<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_model')]
class QueryModel extends Model
{
    public const string TABLE = 'models';

    public string $name;
    public ?int $age = null;

    /**
     * @var ModelCollection<DataModel>
     */
    #[OneToMany(class: DataModel::class)]
    public ModelCollection $children;
}
