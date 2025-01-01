<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('model_a_id')]
class ModelA extends Model
{
    public const string TABLE = 'modelsA';

    public string $name;
    public ?int $age = null;
    public bool $verified = false;

    /**
     * @var ModelCollection<ModelB>
     */
    #[OneToMany(class: ModelB::class)]
    public ModelCollection $children;
}
