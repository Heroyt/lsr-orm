<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('id')]
class DataModel extends Model
{
    public const string TABLE = 'data';

    #[ManyToOne]
    public QueryModel $model;

    public string $description;
    public TestEnum $model_type;
}
